FROM php:8.3-fpm-alpine

ARG UID=1000
ARG GID=1000

RUN apk add --no-cache \
        git curl bash icu-dev oniguruma-dev libzip-dev libpng-dev libxml2-dev \
        mysql-client nodejs npm \
    && apk add --no-cache --virtual .build-deps autoconf gcc g++ make linux-headers \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql mbstring zip exif pcntl bcmath gd intl opcache \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN addgroup -g ${GID} app \
    && adduser -D -u ${UID} -G app -s /bin/sh app

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

USER app

EXPOSE 9000
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
