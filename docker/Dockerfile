# =============================================================================
# POSmeister production image
# =============================================================================
# Multi-stage build:
#   1) node 20    — builds the Vue/Vite frontend into public/build
#   2) composer   — pulls vendor deps without dev requirements
#   3) php-fpm    — final runtime image (nginx runs in its own service)
#
# Result: a slim php-fpm image that contains the compiled frontend and a
# production vendor/ folder. Nothing else.
# =============================================================================

FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci --omit=optional --no-audit --no-fund
COPY resources ./resources
COPY public ./public
COPY vite.config.js postcss.config.js tailwind.config.js* ./
COPY .env.example ./.env
RUN npm run build


FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --no-progress \
    --no-interaction \
    --prefer-dist


FROM php:8.3-fpm-alpine AS runtime
WORKDIR /var/www/html

RUN apk add --no-cache \
        bash curl git icu-dev libpng-dev libjpeg-turbo-dev libwebp-dev \
        libzip-dev mysql-client oniguruma-dev tzdata supervisor \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) bcmath gd intl mbstring opcache pdo_mysql zip \
    && docker-php-ext-enable opcache \
    && rm -rf /var/cache/apk/*

COPY docker/php/php.ini             /usr/local/etc/php/conf.d/posmeister.ini
COPY docker/php/www.conf            /usr/local/etc/php-fpm.d/www.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/entrypoint.sh           /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Application source
COPY . /var/www/html
COPY --from=vendor   /app/vendor             /var/www/html/vendor
COPY --from=frontend /app/public/build       /var/www/html/public/build

RUN composer dump-autoload --optimize --no-dev --no-scripts \
    && mkdir -p storage/app/backups storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 9000
HEALTHCHECK --interval=30s --timeout=4s --start-period=20s --retries=3 \
    CMD php -r "echo @file_get_contents('http://127.0.0.1:9000') ?: '';" || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "-F"]
