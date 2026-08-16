#!/usr/bin/env sh

set -eu

app_url="${APP_URL:-http://localhost:8000}"
status="$(curl --silent --output /dev/null --write-out '%{http_code}' "${app_url}/login")"

if [ "${status}" != "200" ]; then
    echo "Expected ${app_url}/login to return 200, got ${status}" >&2
    exit 1
fi

echo "Nginx accepts the Inertia response headers."
