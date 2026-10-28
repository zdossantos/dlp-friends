#!/usr/bin/env bash
set -euo pipefail
if [[ "${RELEASE_CREATED:-false}" != true ]]; then
    echo 'No new release; skipping deployment.'
    exit 0
fi
: "${COOLIFY_WEB_APPLICATION_UUID:?COOLIFY_WEB_APPLICATION_UUID is required}"
bash "$(dirname "$0")/deploy-coolify-image.sh"
