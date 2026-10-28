#!/usr/bin/env bash
set -euo pipefail
umask 077

: "${COOLIFY_API_URL:?COOLIFY_API_URL is required}"
: "${COOLIFY_APPLICATION_UUID:?COOLIFY_APPLICATION_UUID is required}"
: "${COOLIFY_TOKEN:?COOLIFY_TOKEN is required}"

if [[ ! "${APP_IMAGE:-}" =~ ^ghcr\.io/[a-z0-9._/-]+@sha256:[a-f0-9]{64}$ ]]; then
    echo 'A published GHCR image digest is required.' >&2
    exit 1
fi
if [[ ! "${RELEASE_SHA:-}" =~ ^[a-f0-9]{40}$ ]]; then
    echo 'An exact release commit is required.' >&2
    exit 1
fi

response_file="$(mktemp)"
trap 'rm -f "$response_file"' EXIT
api_url="${COOLIFY_API_URL%/}"

# Discard API response bodies, which can contain runtime configuration.
# Do not retry mutations automatically: a timeout may hide a successful request.
request() {
    curl --fail --silent --show-error --connect-timeout 10 --max-time 60 \
        --request "$1" "$api_url/$2" \
        --header "Authorization: Bearer $COOLIFY_TOKEN" \
        --header 'Content-Type: application/json' \
        --data "$3" --output "$response_file"
}

if [[ -n "${COOLIFY_WEB_APPLICATION_UUID:-}" ]]; then
    [[ "${RELEASE_TAG:-}" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]
    [[ "$COOLIFY_APPLICATION_UUID" =~ ^[a-zA-Z0-9-]+$ && "$COOLIFY_WEB_APPLICATION_UUID" =~ ^[a-zA-Z0-9-]+$ ]]
    [[ "$COOLIFY_APPLICATION_UUID" != "$COOLIFY_WEB_APPLICATION_UUID" ]]
    request GET "applications/$COOLIFY_APPLICATION_UUID" '{}'
    jq -e '.build_pack == "dockercompose" and .docker_compose_location == "/compose.production-support.yaml"' "$response_file" >/dev/null
    environment_id="$(jq -er '.environment_id' "$response_file")"
    request GET "applications/$COOLIFY_WEB_APPLICATION_UUID" '{}'
    jq -e --arg environment "$environment_id" '.build_pack == "dockerimage" and
        (.environment_id | tostring) == $environment and .health_check_enabled == true and
        .health_check_path == "/ready"' "$response_file" >/dev/null
fi

# Coolify also interpolates Compose during its preparation/build phase.
# This public reference must be available there even without build services.
request PATCH "applications/$COOLIFY_APPLICATION_UUID/envs/bulk" \
    "$(jq -nc --arg image "$APP_IMAGE" '{data: [{key: "APP_IMAGE", value: $image,
        is_preview: false, is_literal: true, is_buildtime: true, is_runtime: true}]}')"

# Pin the Compose file too; Coolify reads it from the release tag, not main.
compose_command=""
if [[ -n "${COOLIFY_WEB_APPLICATION_UUID:-}" ]]; then
    # Run the candidate image's migrations, never the old web container's files.
    compose="docker compose -p $COOLIFY_APPLICATION_UUID -f compose.production-support.yaml --env-file .env"
    compose_command="c='$compose'; \$c run --rm --no-deps worker php artisan migrate --force --no-interaction && \$c up -d --wait --wait-timeout 180"
    # Coolify stores this field in varchar(255).
    [[ ${#compose_command} -le 255 ]]
fi
request PATCH "applications/$COOLIFY_APPLICATION_UUID" \
    "$(jq -nc --arg sha "$RELEASE_SHA" --arg command "$compose_command" --arg tag "${RELEASE_TAG:-}" '
        {git_commit_sha: $sha} + if $command == "" then {} else {git_branch: $tag, docker_compose_custom_start_command: $command} end')"

request POST deploy \
    "$(jq -nc --arg uuid "$COOLIFY_APPLICATION_UUID" '{uuid: $uuid, force: false}')"

jq -e --arg uuid "$COOLIFY_APPLICATION_UUID" \
    '.deployments | any(.resource_uuid == $uuid and (.deployment_uuid | type == "string" and length > 0))' \
    "$response_file" >/dev/null
echo 'Coolify accepted the deployment. Check deployment health in Coolify.'

if [[ -n "${COOLIFY_WEB_APPLICATION_UUID:-}" ]]; then
    wait_for_deployment() {
        local uuid="$1" deployment status attempt
        deployment="$(jq -er --arg uuid "$uuid" '.deployments | select(length == 1) | .[0] |
            select(.resource_uuid == $uuid) | .deployment_uuid | select(test("^[a-zA-Z0-9-]+$"))' "$response_file")"
        for attempt in {1..120}; do
            request GET "deployments/$deployment" '{}'
            status="$(jq -er '.status' "$response_file")"
            case "$status" in
                finished) return 0 ;;
                queued|in_progress) sleep 5 ;;
                *) echo "Coolify deployment did not succeed: $status" >&2; return 1 ;;
            esac
        done
        echo 'Timed out waiting for Coolify deployment.' >&2
        return 1
    }

    wait_for_deployment "$COOLIFY_APPLICATION_UUID"
    request PATCH "applications/$COOLIFY_WEB_APPLICATION_UUID" \
        "$(jq -nc --arg image "${APP_IMAGE%@*}" --arg tag "${APP_IMAGE##*@}" '
            {docker_registry_image_name: $image, docker_registry_image_tag: ($tag | sub(":"; "-"))}')"
    request POST deploy "$(jq -nc --arg uuid "$COOLIFY_WEB_APPLICATION_UUID" '{uuid: $uuid, force: false}')"
    wait_for_deployment "$COOLIFY_WEB_APPLICATION_UUID"
    echo 'Coolify finished migrations, support deployment and native rolling web deployment.'
fi
