FROM php:8.5-fpm-bookworm

# Parallel make breaks docker-php-ext-install on some PHP 8.5 images
ENV MAKEFLAGS="-j1"

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev libicu-dev \
    && docker-php-ext-install pdo_pgsql pgsql intl zip \
    && rm -rf /var/lib/apt/lists/*

# PCOV en comptes de Xdebug: aquí només es vol mesurar cobertura, i PCOV és molt
# més ràpid. Queda desactivat per defecte (pcov.enabled=0 a app.ini) perquè
# instrumentar en cada `make test` alentiria la suite sense donar res; `make
# coverage` l'activa amb -d per a la seva execució i prou.
RUN pecl install pcov && docker-php-ext-enable pcov

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

CMD ["php-fpm"]
