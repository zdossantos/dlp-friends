#!/usr/bin/env bash
set -euo pipefail
if [[ "${RELEASE_CREATED:-false}" != true ]]; then
    echo 'No new release; skipping deployment.'
    exit 0
fi
echo 'Production requires the private server procedure: production-release.sh prepare, deploy, verify, resume.' >&2
exit 1
