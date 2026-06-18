FROM php:8.4-cli-alpine

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN docker-php-ext-install pdo_mysql

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    DATABASE_URL="mysql://sym_notes:sym_notes@db:3306/sym_notes?charset=utf8mb4&serverVersion=9.7" \
    COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts

COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev \
    && php bin/console asset-map:compile \
    && mkdir -p var/cache var/log \
    && chown -R www-data:www-data var

USER www-data

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
