FROM php:8.4-cli-alpine AS base

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN docker-php-ext-install pdo_mysql

ENV DATABASE_URL="mysql://sym_notes:sym_notes@db:3306/sym_notes?charset=utf8mb4&serverVersion=9.7" \
    APP_RUNTIME_OPTIONS='{"disable_dotenv":true}' \
    COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock symfony.lock ./

FROM base AS tools

ENV APP_ENV=dev \
    APP_DEBUG=1

RUN composer install --no-interaction --no-progress --prefer-dist --no-scripts

COPY . .

CMD ["sh"]

FROM base AS prod

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    APP_SECRET=build-time-secret \
    APP_SHARE_DIR=var/share \
    DEFAULT_URI=http://localhost:3444

RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts

COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev \
    && php bin/console importmap:install \
    && php bin/console asset-map:compile \
    && mkdir -p var/cache var/log \
    && chown -R www-data:www-data var

USER www-data

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/router.php"]
