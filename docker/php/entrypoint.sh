#!/usr/bin/env sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
  cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi

php artisan key:generate --force --no-interaction || true
php artisan config:clear --no-interaction || true
php artisan migrate --force --no-interaction || true

exec php-fpm -F
