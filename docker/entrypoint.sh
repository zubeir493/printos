#!/bin/sh
set -e

echo "==> Ensuring storage directories exist..."
mkdir -p storage/framework/{sessions,views,cache} \
         storage/logs \
         bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "==> Discovering packages..."
php artisan package:discover --ansi

echo "==> Running migrations..."
php artisan migrate --force --no-interaction

echo "==> Linking public storage..."
php artisan storage:link --force

echo "==> Caching configuration..."
php artisan optimize
php artisan filament:optimize

echo "==> Starting services..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
