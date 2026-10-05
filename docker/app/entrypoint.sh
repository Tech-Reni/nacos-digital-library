#!/bin/sh
# Prepares the app on container start so `docker compose up` is all you need.
set -e

cd /var/www/html

if [ "$1" = "apache2-foreground" ]; then
    [ -f .env ] || cp .env.example .env
    [ -f vendor/autoload.php ] || composer install --no-interaction --prefer-dist

    if ! grep -q '^APP_KEY=base64:' .env; then
        php artisan key:generate --force
    fi

    echo "Waiting for the database..."
    until php -r 'new PDO("mysql:host=".getenv("DB_HOST").";port=3306", getenv("DB_USERNAME"), getenv("DB_PASSWORD"));' >/dev/null 2>&1; do
        sleep 2
    done

    php artisan migrate --force
    php artisan db:seed --force

    chown -R www-data:www-data storage bootstrap/cache
fi

exec "$@"
