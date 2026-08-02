# Production / deploy image (Fly.io, Railway). Local compose uses docker/php/Dockerfile.
FROM php:8.5-fpm-bookworm

ENV MAKEFLAGS="-j1"

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev libicu-dev \
    && docker-php-ext-install pdo_pgsql pgsql intl zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-progress --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p var/cache var/log \
    && chown -R www-data:www-data var

USER www-data

CMD ["php-fpm"]
