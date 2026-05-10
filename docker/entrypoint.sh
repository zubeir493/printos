#!/bin/sh
set -e

echo "==> Ensuring storage directories exist..."
mkdir -p storage/framework/sessions \
         storage/framework/views \
         storage/framework/cache \
         storage/logs \
         bootstrap/cache

echo "==> Running migrations..."
php artisan migrate --force --no-interaction

echo "==> Linking public storage..."
php artisan storage:link --force

echo "==> Caching configuration..."
php artisan package:discover --ansi --no-interaction || true
php artisan optimize
php artisan filament:optimize

echo "==> Starting services..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
