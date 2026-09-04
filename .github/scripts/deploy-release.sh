#!/usr/bin/env bash
set -euo pipefail

if [[ "${RELEASE_CREATED:-false}" != true ]]; then
    echo 'No new release; skipping deployment.'
    exit 0
fi

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

# Coolify also interpolates Compose during its preparation/build phase.
# This public reference must be available there even without build services.
request PATCH "applications/$COOLIFY_APPLICATION_UUID/envs/bulk" \
    "$(jq -nc --arg image "$APP_IMAGE" '{data: [{key: "APP_IMAGE", value: $image,
        is_preview: false, is_literal: true, is_buildtime: true, is_runtime: true}]}')"

# Pin the Compose file too, even if main has advanced since this release.
request PATCH "applications/$COOLIFY_APPLICATION_UUID" \
    "$(jq -nc --arg sha "$RELEASE_SHA" '{git_commit_sha: $sha}')"

request POST deploy \
    "$(jq -nc --arg uuid "$COOLIFY_APPLICATION_UUID" '{uuid: $uuid, force: false}')"

jq -e --arg uuid "$COOLIFY_APPLICATION_UUID" \
    '.deployments | any(.resource_uuid == $uuid and (.deployment_uuid | type == "string" and length > 0))' \
    "$response_file" >/dev/null
echo 'Coolify accepted the release deployment. Check deployment health in Coolify.'
