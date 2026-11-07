# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Base commune : FrankenPHP (PHP + Caddy) et les extensions
# ---------------------------------------------------------------------------
# PHP 8.5 par défaut ; bin/ci --php 8.4 s'en sert pour tester aussi en 8.4, comme la CI.
ARG PHP_VERSION=8.5

FROM dunglas/frankenphp:1-php${PHP_VERSION} AS base

ARG USER_ID=1000
ARG GROUP_ID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    curl \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
    pdo_mysql \
    intl \
    zip \
    opcache \
    redis \
    gd \
    exif \
    xsl \
    amqp

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Assez de marge pour les photos de 5 Mo acceptées par le site.
COPY docker/php/app.ini $PHP_INI_DIR/conf.d/zz-app.ini

# Même identifiant que votre utilisateur, pour ne pas se retrouver avec des fichiers à root.
RUN groupadd -g ${GROUP_ID} appuser \
    && useradd -u ${USER_ID} -g appuser -m -s /bin/bash appuser

WORKDIR /app

# ---------------------------------------------------------------------------
# Développement : le code est monté depuis votre dossier
# ---------------------------------------------------------------------------
FROM base AS dev

COPY docker/caddy/Caddyfile.dev /etc/frankenphp/Caddyfile
RUN chown -R appuser:appuser /app

# ---------------------------------------------------------------------------
# Production : tout est embarqué dans l'image
# ---------------------------------------------------------------------------
FROM base AS prod

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    SERVER_NAME=:8080

RUN cp $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini
COPY docker/php/prod.ini $PHP_INI_DIR/conf.d/zz-prod.ini
COPY docker/caddy/Caddyfile.prod /etc/frankenphp/Caddyfile

# Les dépendances d'abord, pour que Docker les garde en cache tant que composer.lock ne bouge pas.
COPY --chown=appuser:appuser composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --prefer-dist --no-scripts --no-autoloader --no-progress --no-interaction

COPY --chown=appuser:appuser . .

# Le .env ne sert qu'aux valeurs par défaut : les vraies viennent du .env.prod du serveur,
# qui n'entre jamais dans l'image.
RUN cp .env.example .env \
    && composer dump-autoload --no-dev --classmap-authoritative \
    && php bin/console importmap:install \
    && php bin/console asset-map:compile \
    && php bin/console assets:install public \
    && php bin/console cache:warmup \
    && mkdir -p var/share public/uploads/themes public/uploads/avatars storage/pending \
    && chown -R appuser:appuser var public storage /data/caddy /config/caddy

# Le port 8080 permet de tourner sans être root.
USER appuser
EXPOSE 8080
