FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    git curl zip unzip \
    libzip-dev postgresql-dev \
    icu-dev oniguruma-dev \
    autoconf g++ make \
    nodejs npm

RUN docker-php-ext-install \
    pdo pdo_pgsql zip intl opcache mbstring

RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini