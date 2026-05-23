#!/usr/bin/env bash
# POSmeister entrypoint
# ---------------------
# Runs on container start. Two responsibilities:
#   1) Bootstrap writable folders + config cache
#   2) Optionally run migrations (set RUN_MIGRATIONS=1 in compose)
set -euo pipefail

cd /var/www/html

# Ensure runtime dirs exist (volume mounts can shadow what the image has)
mkdir -p storage/app/backups \
         storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Config cache + view cache for hot start
if [ "${LARAVEL_CACHE:-1}" = "1" ]; then
    php artisan config:cache  || true
    php artisan route:cache   || true
    php artisan view:cache    || true
fi

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    php artisan migrate --force || true
fi

if [ "${SEED_DEMO:-0}" = "1" ]; then
    php artisan posmeister:seed-demo || true
fi

exec "$@"
