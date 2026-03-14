FROM composer:2.8 AS vendor
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    bash \
    git \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    unzip \
    && docker-php-ext-install pdo_mysql mbstring bcmath intl zip \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000

CMD ["sh", "/usr/local/bin/entrypoint.sh"]
