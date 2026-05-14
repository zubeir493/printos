#!/usr/bin/env sh
set -eu

role="${1:-web}"

prepare_laravel() {
    if [ -z "${APP_KEY:-}" ]; then
        echo "APP_KEY is required. Generate one with: php artisan key:generate --show"
        exit 1
    fi

    php artisan storage:link --force >/dev/null 2>&1 || true

    if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
        php artisan migrate --force --no-interaction
    fi

    php artisan config:cache
    php artisan route:cache
    php artisan event:cache
    php artisan view:cache
    php artisan filament:optimize
}

case "$role" in
    web)
        prepare_laravel
        exec php artisan octane:start \
            --server="${OCTANE_SERVER:-swoole}" \
            --host=0.0.0.0 \
            --port="${PORT:-8000}" \
            --workers="${OCTANE_WORKERS:-4}" \
            --task-workers="${OCTANE_TASK_WORKERS:-2}" \
            --max-requests="${OCTANE_MAX_REQUESTS:-500}"
        ;;
    queue)
        prepare_laravel
        exec php artisan queue:work \
            --sleep="${QUEUE_SLEEP:-3}" \
            --tries="${QUEUE_TRIES:-3}" \
            --timeout="${QUEUE_TIMEOUT:-90}"
        ;;
    scheduler)
        prepare_laravel
        while true; do
            php artisan schedule:run --verbose --no-interaction
            sleep 60
        done
        ;;
    *)
        exec "$@"
        ;;
esac
