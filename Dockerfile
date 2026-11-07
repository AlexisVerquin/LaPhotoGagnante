# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Base commune : FrankenPHP (PHP + Caddy) et extensions
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.5 AS base

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

# Limites d'upload alignées sur l'application (photos de 5 Mo max)
COPY docker/php/app.ini $PHP_INI_DIR/conf.d/zz-app.ini

# Utilisateur non-root (même UID que l'hôte en dev : pas de fichiers appartenant à root)
RUN groupadd -g ${GROUP_ID} appuser \
    && useradd -u ${USER_ID} -g appuser -m -s /bin/bash appuser

WORKDIR /app

# ---------------------------------------------------------------------------
# Développement : le code est monté en volume (docker-compose.yml)
# ---------------------------------------------------------------------------
FROM base AS dev

COPY docker/caddy/Caddyfile.dev /etc/frankenphp/Caddyfile
RUN chown -R appuser:appuser /app

# ---------------------------------------------------------------------------
# Production : code, dépendances et assets embarqués dans l'image
# ---------------------------------------------------------------------------
FROM base AS prod

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    SERVER_NAME=:8080

RUN cp $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini
COPY docker/php/prod.ini $PHP_INI_DIR/conf.d/zz-prod.ini
COPY docker/caddy/Caddyfile.prod /etc/frankenphp/Caddyfile

# Dépendances d'abord : couche mise en cache tant que composer.lock ne change pas
COPY --chown=appuser:appuser composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --prefer-dist --no-scripts --no-autoloader --no-progress --no-interaction

COPY --chown=appuser:appuser . .

# .env sert uniquement de valeurs par défaut : les vraies valeurs viennent de l'environnement
# (fichier .env.prod sur le serveur, jamais dans l'image)
RUN cp .env.example .env \
    && composer dump-autoload --no-dev --classmap-authoritative \
    && php bin/console importmap:install \
    && php bin/console asset-map:compile \
    && php bin/console assets:install public \
    && php bin/console cache:warmup \
    && mkdir -p var/share public/uploads/themes public/uploads/avatars storage/pending \
    && chown -R appuser:appuser var public storage /data/caddy /config/caddy

# Port 8080 (> 1024) : FrankenPHP tourne sans être root
USER appuser
EXPOSE 8080
