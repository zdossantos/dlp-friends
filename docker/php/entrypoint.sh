#!/bin/sh
set -eu

cd /var/www/html

case "${1:-web}" in
    web)
        php-fpm -D
        exec nginx -g 'daemon off;'
        ;;
    worker)
        exec php artisan queue:work --sleep=3 --tries=3 --timeout=90 --memory=256
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
