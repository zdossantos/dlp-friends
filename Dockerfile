# syntax=docker/dockerfile:1.7

FROM oven/bun:1.3.14-alpine AS bun

FROM php:8.4.27-cli-alpine3.24 AS build

WORKDIR /app

RUN apk add --no-cache \
        curl \
        git \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        linux-headers \
        oniguruma-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" bcmath exif gd intl pcntl pdo_mysql sockets zip \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
COPY --from=bun /usr/local/bin/bun /usr/local/bin/bun
COPY . .

ARG VITE_APP_NAME="DLP Friends"
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT
ARG VITE_REVERB_SCHEME

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --classmap-authoritative \
    && bun install --frozen-lockfile \
    && bun run build \
    && rm -rf node_modules

FROM php:8.4.27-fpm-alpine3.24 AS runtime

WORKDIR /var/www/html

ENV PHP_FPM_MAX_CHILDREN=10

RUN apk add --no-cache \
        curl \
        icu-libs \
        libjpeg-turbo \
        libjpeg-turbo-dev \
        libpng \
        libpng-dev \
        libwebp \
        libwebp-dev \
        libzip \
        nginx \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        linux-headers \
        oniguruma-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" bcmath exif gd intl opcache pcntl pdo_mysql sockets zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS icu-dev libjpeg-turbo-dev libpng-dev libwebp-dev libzip-dev linux-headers oniguruma-dev \
    && rm -rf /tmp/pear /var/cache/apk/* /var/lib/nginx/html

COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/php/drain.conf /usr/local/etc/php-fpm.d/zz-dlp-drain.conf
COPY docker/php/capacity.conf /usr/local/etc/php-fpm.d/zz-dlp-capacity.conf
COPY docker/php/entrypoint.sh /usr/local/bin/dlp-entrypoint
COPY --from=build --chown=www-data:www-data /app /var/www/html

RUN chmod +x /usr/local/bin/dlp-entrypoint \
    && cp -a public/build/assets /opt/dlp-build-assets \
    && chown -R www-data:www-data storage bootstrap/cache \
    && mkdir -p /run/nginx

EXPOSE 80 8080

STOPSIGNAL SIGQUIT

ENTRYPOINT ["/usr/local/bin/dlp-entrypoint"]
CMD ["web"]

HEALTHCHECK --interval=5s --timeout=5s --start-period=20s --retries=1 \
    CMD curl --fail --silent --max-time 4 http://127.0.0.1/ready >/dev/null || exit 1
