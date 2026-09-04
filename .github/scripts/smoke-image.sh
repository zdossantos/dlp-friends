#!/usr/bin/env bash
set -euo pipefail

image="${1:?Usage: smoke-image.sh IMAGE}"
container="$(docker run --detach --pull missing \
    --env APP_ENV=production \
    --env APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    --env APP_DEBUG=false \
    --env APP_MAINTENANCE_DRIVER=file \
    --env CACHE_STORE=array \
    --env SESSION_DRIVER=array \
    --env QUEUE_CONNECTION=sync \
    --env MAIL_MAILER=array \
    "$image")"
trap 'docker rm --force "$container" >/dev/null' EXIT

for attempt in $(seq 1 30); do
    if docker exec "$container" curl --fail --silent http://127.0.0.1/up >/dev/null; then
        docker exec "$container" test -s public/build/manifest.json
        docker exec "$container" sh -c 'test ! -e .env && test ! -e node_modules && test ! -e .git'
        echo 'Runtime image starts and serves /up with compiled assets.'
        exit 0
    fi
    if [[ "$(docker inspect --format '{{.State.Running}}' "$container")" != true ]]; then
        break
    fi
    sleep 2
done

docker logs "$container"
echo 'Runtime image did not become healthy.' >&2
exit 1
