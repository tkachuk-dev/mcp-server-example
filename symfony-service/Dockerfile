# syntax=docker/dockerfile:1

FROM dunglas/frankenphp:1-php8.4 AS base

WORKDIR /app

RUN install-php-extensions pdo_pgsql intl opcache redis zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY frankenphp/conf.d/app.ini $PHP_INI_DIR/conf.d/zz-app.ini
COPY frankenphp/Caddyfile /etc/frankenphp/Caddyfile

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    # Symfony Runtime switches to its FrankenPHP worker runner when the kernel runs as a worker.
    FRANKENPHP_CONFIG="worker /app/public/index.php"

EXPOSE 80 8080

HEALTHCHECK --interval=10s --timeout=3s --retries=5 \
    CMD php -r 'exit(false === @file_get_contents("http://127.0.0.1/health/live") ? 1 : 0);'

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

# Local development: includes dev dependencies (fixtures, test tools).
FROM base AS dev

ENV APP_ENV=dev

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
RUN composer dump-autoload --no-interaction \
    && mkdir -p var && chown -R www-data:www-data var

# Production: no dev dependencies, warmed cache, configuration only from env.
FROM base AS prod

ENV APP_ENV=prod \
    APP_DEBUG=0

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
RUN rm -rf tests .env.test \
    && composer dump-autoload --no-dev --classmap-authoritative --no-interaction \
    && php bin/console cache:warmup \
    && chown -R www-data:www-data var
