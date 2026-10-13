#!/bin/sh
set -e

php artisan config:cache
php artisan route:cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
