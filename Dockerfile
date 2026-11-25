FROM composer:2 AS composer

FROM php:8.5-apache-bookworm AS php-dependencies

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl fonts-dejavu-core git libfreetype6-dev libicu-dev libjpeg62-turbo-dev \
        libpng-dev libzip-dev mariadb-client unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath gd intl opcache pcntl pdo_mysql sockets zip \
    && pecl install redis-6.3.0 \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* /tmp/pear \
    && a2enmod rewrite \
    && sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|' /etc/apache2/sites-available/000-default.conf \
    && printf '%s\n' '<Directory /var/www/html/public>' '    AllowOverride All' '    Options -Indexes +FollowSymLinks' '</Directory>' > /etc/apache2/conf-available/printos.conf \
    && a2enconf printos \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf '%s\n' 'upload_max_filesize=64M' 'post_max_size=80M' 'memory_limit=512M' 'log_errors=On' 'error_log=/dev/stderr' > "$PHP_INI_DIR/conf.d/printos.ini"

WORKDIR /var/www/html
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader

FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --include=dev
COPY . .
COPY --from=php-dependencies /var/www/html/vendor ./vendor
RUN npm run build && npm prune --omit=dev --no-audit --no-fund

FROM php-dependencies AS production
COPY . .
COPY --from=assets /app/public/build ./public/build
COPY --from=assets /usr/local/bin/node /usr/local/bin/node
COPY --from=assets /app/node_modules ./node_modules
RUN mkdir -p bootstrap/cache storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-dev --optimize --no-interaction \
    && php artisan storage:link --no-interaction \
    && chown -R www-data:www-data bootstrap/cache storage
