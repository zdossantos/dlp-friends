#!/bin/sh
set -eu

cd /var/www/html

case "${1:-web}" in
    worker|scheduler|reverb)
        attempts="${DEPLOYMENT_READINESS_ATTEMPTS:-30}"
        case "$attempts" in ''|*[!0-9]*) exit 64 ;; esac
        [ "$attempts" -ge 1 ] && [ "$attempts" -le 60 ] || exit 64
        until php artisan deployment:check --json >/dev/null 2>&1; do
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
        php-fpm -D
        exec nginx -g 'daemon off;'
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
