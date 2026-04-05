#!/usr/bin/env sh
set -e

cd /var/www/html

UPLOAD_MAX_FILESIZE="${UPLOAD_MAX_FILESIZE:-20M}"
POST_MAX_SIZE="${POST_MAX_SIZE:-20M}"
PHP_MEMORY_LIMIT="${PHP_MEMORY_LIMIT:-256M}"

cat >/usr/local/etc/php/conf.d/zz-upload-limits.ini <<EOF
upload_max_filesize=${UPLOAD_MAX_FILESIZE}
post_max_size=${POST_MAX_SIZE}
memory_limit=${PHP_MEMORY_LIMIT}
EOF

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
