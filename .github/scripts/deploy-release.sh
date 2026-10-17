#!/usr/bin/env bash
set -euo pipefail

if [[ "${RELEASE_CREATED:-false}" != true ]]; then
    echo 'No new release; skipping deployment.'
    exit 0
fi

bash "$(dirname "$0")/deploy-coolify-image.sh"
