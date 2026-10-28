#!/bin/sh
set -eu

cd /var/www/html

case "${1:-web}" in
    worker|scheduler|reverb)
        attempts="${DEPLOYMENT_READINESS_ATTEMPTS:-30}"
        case "$attempts" in ''|*[!0-9]*) exit 64 ;; esac
        [ "$attempts" -ge 1 ] && [ "$attempts" -le 60 ] || exit 64
        # Beta lifecycle verifies every process before reopening HTTP access.
        readiness() {
            if [ "${APP_ENV:-}" = beta ]; then
                php artisan deployment:check --json --allow-maintenance
            else
                php artisan deployment:check --json
            fi
        }
        until readiness >/dev/null 2>&1; do
            attempts=$((attempts - 1))
            if [ "$attempts" -eq 0 ]; then
                echo 'Deployment not ready; background process remains stopped.' >&2
                exit 75
            fi
            sleep 2
        done
        ;;
esac

case "${1:-web}" in
    web)
        # Only hashed assets are shared; each image keeps its own manifest.
        assets_tmp=$(mktemp -d public/build/assets/.publish.XXXXXX)
        trap 'rm -rf "$assets_tmp"' EXIT HUP INT TERM
        for asset in /opt/dlp-build-assets/*; do
            name=${asset##*/}
            if ! cmp -s "$asset" "public/build/assets/$name"; then
                cp -p "$asset" "$assets_tmp/$name"
                mv -f "$assets_tmp/$name" "public/build/assets/$name"
            fi
        done
        rm -rf "$assets_tmp"
        trap - EXIT HUP INT TERM
        fpm_pid=''
        nginx_pid=''
        stop_web() {
            trap '' TERM INT QUIT
            if [ -n "$nginx_pid" ]; then
                kill -QUIT "$nginx_pid" 2>/dev/null || true
                wait "$nginx_pid" || true
            fi
            if [ -n "$fpm_pid" ]; then
                kill -QUIT "$fpm_pid" 2>/dev/null || true
                wait "$fpm_pid" || true
            fi
            exit 0
        }
        trap stop_web TERM INT QUIT
        php-fpm --nodaemonize &
        fpm_pid=$!
        nginx -g 'daemon off;' &
        nginx_pid=$!
        status=0
        wait "$nginx_pid" || status=$?
        kill -QUIT "$fpm_pid" 2>/dev/null || true
        wait "$fpm_pid" || true
        exit "$status"
        ;;
    worker)
        exec php artisan queue:work --sleep=3 --tries=3 --timeout=90 --memory="${QUEUE_WORKER_MEMORY:-256}"
        ;;
    scheduler)
        exec php artisan schedule:work
        ;;
    reverb)
        exec php artisan reverb:start --host=0.0.0.0 --port=8080
        ;;
    *)
        exec "$@"
        ;;
esac
