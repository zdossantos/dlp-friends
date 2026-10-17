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
bash "$(dirname "$0")/deploy-coolify-image.sh"
echo 'Beta deployment requested. Check deployment:check separately; migrations and maintenance remain manual.'
