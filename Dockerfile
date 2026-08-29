FROM dunglas/frankenphp

ARG USER_ID=1000
ARG GROUP_ID=1000

# Install system packages
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN install-php-extensions \
    pdo_mysql \
    intl \
    zip \
    opcache \
    redis \
    gd \
    xsl \
    amqp

COPY Caddyfile /etc/frankenphp/Caddyfile

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Create a non-root user matching the host user (avoids root-owned files)
RUN groupadd -g ${GROUP_ID} appuser \
    && useradd -u ${USER_ID} -g appuser -m -s /bin/bash appuser

# Working directory
WORKDIR /app
RUN chown -R appuser:appuser /app
