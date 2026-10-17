#!/usr/bin/env bash
set -euo pipefail
umask 077
: "${BETA_CANDIDATE_FILE:?BETA_CANDIDATE_FILE is required}"
: "${COOLIFY_BETA_APPLICATION_UUID:?COOLIFY_BETA_APPLICATION_UUID is required}"
: "${COOLIFY_PRODUCTION_APPLICATION_UUID:?COOLIFY_PRODUCTION_APPLICATION_UUID is required}"
[[ "$COOLIFY_BETA_APPLICATION_UUID" != "$COOLIFY_PRODUCTION_APPLICATION_UUID" ]] || exit 1
directory="$(mktemp -d)"
trap 'rm -rf "$directory"' EXIT
# This runs inside the serialized deployment job, immediately before mutations.
bash "$(dirname "$0")/select-beta-candidate.sh" > "$directory/current.json"
if [[ "$(jq -c . "$directory/current.json")" == null ]]; then
    echo 'No validated Release PR; keeping the current beta.'
    exit 0
fi
jq -e --slurpfile expected "$BETA_CANDIDATE_FILE" '. == $expected[0]' "$directory/current.json" >/dev/null
export RELEASE_CREATED=true RELEASE_SHA="$(jq -r .sha "$directory/current.json")"
export COOLIFY_APPLICATION_UUID="$COOLIFY_BETA_APPLICATION_UUID"
if [[ -n "${BETA_SSH_HOST:-}" ]]; then
    : "${BETA_SSH_PRIVATE_KEY:?BETA_SSH_PRIVATE_KEY is required}"
    : "${BETA_SSH_KNOWN_HOSTS:?BETA_SSH_KNOWN_HOSTS is required}"
    [[ "$BETA_SSH_HOST" =~ ^[a-zA-Z0-9][a-zA-Z0-9.-]*$ ]] || exit 1
    [[ "${APP_IMAGE:-}" =~ ^ghcr\.io/zdossantos/dlp-friends@sha256:[a-f0-9]{64}$ ]] || exit 1
    [[ "$RELEASE_SHA" =~ ^[a-f0-9]{40}$ ]] || exit 1
    # Coolify reloads Compose from branch HEAD, ignoring git_commit_sha here.
    # Create once, never update/force this candidate branch; verify existing refs.
    branch="chore/beta-candidates/$RELEASE_SHA"
    api_url="${GITHUB_API_URL:-https://api.github.com}"
    jq -nc --arg ref "refs/heads/$branch" --arg sha "$RELEASE_SHA" '{ref: $ref, sha: $sha}' > "$directory/ref.json"
    status="$(printf 'header = "Authorization: Bearer %s"\n' "$GH_TOKEN" |
        curl --config - --silent --show-error --connect-timeout 10 --max-time 60 \
            --request POST --header 'Content-Type: application/json' --data-binary "@$directory/ref.json" \
            --output "$directory/ref-response.json" --write-out '%{http_code}' \
            "$api_url/repos/$GITHUB_REPOSITORY/git/refs")"
    [[ "$status" == 201 || "$status" == 422 ]] || exit 1
    printf 'header = "Authorization: Bearer %s"\n' "$GH_TOKEN" |
        curl --config - --fail --silent --show-error --connect-timeout 10 --max-time 60 \
            "$api_url/repos/$GITHUB_REPOSITORY/git/ref/heads/$branch" > "$directory/ref-response.json"
    jq -e --arg sha "$RELEASE_SHA" '.object.sha == $sha' "$directory/ref-response.json" >/dev/null
    printf '%s\n' "$BETA_SSH_PRIVATE_KEY" > "$directory/key"
    printf '%s\n' "$BETA_SSH_KNOWN_HOSTS" > "$directory/known_hosts"
    jq -nc --arg uuid "$COOLIFY_BETA_APPLICATION_UUID" --arg sha "$RELEASE_SHA" --arg image "$APP_IMAGE" \
        '{application_uuid: $uuid, sha: $sha, image: $image}' > "$directory/request.json"
    ssh -i "$directory/key" -o BatchMode=yes -o IdentitiesOnly=yes \
        -o StrictHostKeyChecking=yes -o "UserKnownHostsFile=$directory/known_hosts" \
        -o ConnectTimeout=15 -o ServerAliveInterval=15 -o ServerAliveCountMax=3 \
        "dlp-beta-deploy@$BETA_SSH_HOST" < "$directory/request.json" > "$directory/response.json"
    jq -e '.deployment_uuid | type == "string" and length > 0' "$directory/response.json" >/dev/null
else
    bash "$(dirname "$0")/deploy-coolify-image.sh"
fi
echo 'Beta deployment requested. Check deployment:check separately; migrations and maintenance remain manual.'
