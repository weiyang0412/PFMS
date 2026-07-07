#!/bin/sh
set -eu

export DB_HOST="${DB_HOST:-${MYSQLHOST:-}}"
export DB_PORT="${DB_PORT:-${MYSQLPORT:-}}"
export DB_DATABASE="${DB_DATABASE:-${MYSQLDATABASE:-}}"
export DB_USERNAME="${DB_USERNAME:-${MYSQLUSER:-}}"
export DB_PASSWORD="${DB_PASSWORD:-${MYSQLPASSWORD:-}}"

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is missing; generating a runtime key."
    export APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
fi

mkdir -p /app/storage/framework/cache/data \
    /app/storage/framework/sessions \
    /app/storage/framework/views \
    /app/storage/logs

chmod -R 775 /app/storage /app/bootstrap/cache 2>/dev/null || true

wait_for_db() {
    if [ -z "${DB_HOST:-}" ] || [ -z "${DB_PORT:-}" ]; then
        echo "DB_HOST or DB_PORT is missing; skipping database wait."
        return 1
    fi

    echo "Waiting for database at ${DB_HOST}:${DB_PORT} ..."
    attempt=1
    while [ "$attempt" -le 60 ]; do
        if php -r '$host = getenv("DB_HOST"); $port = (int) getenv("DB_PORT"); $socket = @fsockopen($host, $port, $errno, $errstr, 2); if ($socket) { fclose($socket); exit(0); } exit(1);'; then
            echo "Database is reachable."
            return 0
        fi

        sleep 2
        attempt=$((attempt + 1))
    done

    echo "Database did not become reachable in time."
    return 1
}

if wait_for_db; then
    php artisan migrate --force
else
    echo "Skipping migrations because the database is not reachable yet."
fi

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
