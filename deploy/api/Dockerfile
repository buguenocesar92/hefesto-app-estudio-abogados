# ---------------------------------------------------------------------------
# Imagen de la API (Laravel + PostgreSQL).
# ---------------------------------------------------------------------------
# Versiones fijadas a propósito: una imagen que se mueve sola hace que una
# instalación que funcionaba ayer falle hoy sin que nadie haya tocado nada.
# ---------------------------------------------------------------------------

FROM php:8.3-cli-alpine AS base

# `postgresql-dev` trae pg_config, necesario para compilar la extensión de
# PostgreSQL de PHP. Se instala en esta etapa y se elimina en la final.
RUN apk add --no-cache \
        postgresql-dev \
        libpq-dev \
        icu-dev \
        oniguruma-dev \
        openssl-dev \
        $PHPIZE_DEPS

RUN apk add --no-cache \
        postgresql-client \
        libpq \
        icu-libs \
        oniguruma \
        libssl3 \
        nginx \
        supervisor

COPY --from=php:8.3-cli-alpine /usr/local/bin/docker-php-ext-install pdo_pgsql pgsql intl mbstring pcntl opcache

RUN apk del $PHPIZE_DEPS

WORKDIR /var/www/html

COPY api/composer.json api/composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY api/ ./
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

# ---------------------------------------------------------------------------
# Imagen final
# ---------------------------------------------------------------------------
FROM base AS php

USER root

COPY deploy/api/docker/nginx.conf /etc/nginx/http.d/default.conf
COPY deploy/api/docker/php.ini /usr/local/etc/php/conf.d/estudio.ini
COPY deploy/api/docker/supervisord.conf /etc/supervisord.conf
COPY deploy/api/docker/entrypoint.sh /usr/local/bin/entrada

RUN chmod +x /usr/local/bin/entrada

# Puerto interno de PHP-FPM. Nginx publica el 8080.
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrada"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]