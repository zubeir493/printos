# syntax=docker/dockerfile:1

FROM php:8.3-cli-bookworm AS php-base

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        fontconfig \
        fonts-dejavu \
        git \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libssl-dev \
        libxml2-dev \
        libzip-dev \
        procps \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        pcntl \
        pdo_mysql \
        sockets \
        zip \
    && printf "\n" | pecl install redis swoole \
    && docker-php-ext-enable redis swoole \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

FROM php-base AS vendor

WORKDIR /app

COPY . .
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

FROM node:22-bookworm-slim AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY --from=vendor /app/vendor ./vendor
COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build

FROM php-base AS app

ENV APP_ENV=production \
    APP_DEBUG=false \
    OCTANE_SERVER=swoole \
    OCTANE_WORKERS=4 \
    OCTANE_TASK_WORKERS=2 \
    OCTANE_MAX_REQUESTS=500 \
    PORT=8000

WORKDIR /var/www/html

COPY --from=vendor --chown=www-data:www-data /app ./
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
COPY --chown=www-data:www-data docker/entrypoint.sh /usr/local/bin/start-container
COPY docker/php/production.ini /usr/local/etc/php/conf.d/production.ini

RUN chmod +x /usr/local/bin/start-container \
    && mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache public/build

USER www-data

EXPOSE 8000

ENTRYPOINT ["start-container"]
CMD ["web"]
