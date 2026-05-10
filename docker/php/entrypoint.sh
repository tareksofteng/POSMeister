#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist
fi

if ! grep -q '^APP_KEY=base64' .env 2>/dev/null; then
    php artisan key:generate --force
fi

# wait for db to accept connections before continuing
if [ -n "$DB_HOST" ]; then
    tries=0
    until php -r "exit(@(new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: 3306), getenv('DB_USERNAME'), getenv('DB_PASSWORD'))) ? 0 : 1);" 2>/dev/null; do
        tries=$((tries + 1))
        if [ "$tries" -gt 30 ]; then
            echo "Database not reachable after 30s, giving up"
            exit 1
        fi
        sleep 1
    done
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
