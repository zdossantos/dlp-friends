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
        docker exec -i "$container" php <<'PHP'
<?php
$source = imagecreatetruecolor(640, 360);
foreach (['jpeg', 'png', 'webp'] as $format) {
    $encoder = 'image'.$format;
    if (! function_exists($encoder)) {
        throw new RuntimeException("Missing GD encoder: {$format}");
    }
    ob_start();
    $encoder($source);
    $contents = ob_get_clean();
    $decoded = imagecreatefromstring($contents);
    if ($decoded === false || imagesx($decoded) !== 640 || imagesy($decoded) !== 360) {
        throw new RuntimeException("Cannot decode {$format} uploads");
    }
    imagedestroy($decoded);
}
imagedestroy($source);
if (ini_parse_quantity(ini_get('upload_max_filesize')) < 5 * 1024 * 1024
    || ini_parse_quantity(ini_get('post_max_size')) <= 5 * 1024 * 1024) {
    throw new RuntimeException('PHP upload limits do not support 5 MiB images');
}
echo "Runtime accepts JPEG, PNG and WebP with 5 MiB uploads.\n";
PHP
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
