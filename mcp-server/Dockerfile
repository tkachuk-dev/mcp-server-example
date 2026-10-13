# syntax=docker/dockerfile:1
FROM dunglas/frankenphp:1-php8.5-alpine

WORKDIR /app

RUN install-php-extensions intl opcache apcu zip

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/app.ini $PHP_INI_DIR/conf.d/zz-app.ini
COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    SERVER_NAME=:80 \
    COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress --no-interaction

COPY bin bin/
COPY config config/
COPY public public/
COPY src src/
COPY .env ./

RUN composer dump-autoload --no-dev --classmap-authoritative \
    && php bin/console cache:warmup \
    && chown -R www-data:www-data var

EXPOSE 80

HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=3 \
    CMD wget -qO- http://127.0.0.1/health/live >/dev/null || exit 1
