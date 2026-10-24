#!/usr/bin/env bash
set -euo pipefail

export APP_ENV=testing REALTIME_BROWSER_TESTS=true BROADCAST_CONNECTION=reverb

# Use only with the disposable testing database and matching Vite Reverb assets.
php tests/Support/verify-test-database.php
mkdir -p storage/logs
php artisan reverb:start --env=testing --host=127.0.0.1 --port="$REVERB_PORT" > storage/logs/reverb-testing.log 2>&1 &
reverb_pid=$!
trap 'kill "$reverb_pid" 2>/dev/null || true; wait "$reverb_pid" 2>/dev/null || true' EXIT

for attempt in {1..30}; do
    if ! kill -0 "$reverb_pid" 2>/dev/null; then
        cat storage/logs/reverb-testing.log
        exit 1
    fi
    if curl --silent --output /dev/null "http://127.0.0.1:$REVERB_PORT"; then
        ./vendor/bin/pest tests/Realtime --display-warnings "$@"
        exit 0
    fi
    sleep 1
done

cat storage/logs/reverb-testing.log
echo 'Reverb did not become ready within 30 seconds.' >&2
exit 1
