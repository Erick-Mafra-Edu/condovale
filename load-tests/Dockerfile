FROM php:8.3-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

COPY laravel/composer.json laravel/composer.lock ./
RUN composer install --no-interaction --no-progress --prefer-dist --no-dev

COPY laravel/ ./

EXPOSE 8000
