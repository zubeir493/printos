#!/bin/sh
set -e

echo "==> Running migrations..."
php artisan migrate --force --no-interaction

echo "==> Caching configuration..."
php artisan optimize
php artisan filament:optimize

echo "==> Starting services..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
