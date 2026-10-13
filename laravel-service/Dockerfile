# syntax=docker/dockerfile:1

FROM composer:2 AS vendor
# Local compose builds set INSTALL_DEV=1 so the demo seeder (faker, factories) can run; production keeps --no-dev.
ARG INSTALL_DEV=0
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install $([ "$INSTALL_DEV" = "1" ] || echo --no-dev) --no-scripts --no-autoloader --no-interaction --prefer-dist --ignore-platform-req=ext-redis
COPY . .
RUN composer dump-autoload $([ "$INSTALL_DEV" = "1" ] || echo --no-dev) --optimize --classmap-authoritative

FROM dunglas/frankenphp:1-php8.4 AS app
WORKDIR /app

RUN install-php-extensions pdo_pgsql redis intl opcache pcntl zip \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'date.timezone=UTC\nexpose_php=Off\nopcache.validate_timestamps=0\n' > "$PHP_INI_DIR/conf.d/zz-app.ini"

COPY --from=vendor /app /app
COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

# Run as www-data; the capability lets the unprivileged user bind :80.
RUN chmod +x /usr/local/bin/entrypoint \
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && mkdir -p /data/caddy /config/caddy \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache /data/caddy /config/caddy

USER www-data

# 80: internal listener (cluster network). 8000: public listener for the ingress, /internal/* → 404.
EXPOSE 80 8000

ENTRYPOINT ["entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
