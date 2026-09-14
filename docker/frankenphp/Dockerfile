FROM dunglas/frankenphp:1-php8.4

ENV APP_ENV=dev \
    COMPOSER_ALLOW_SUPERUSER=1 \
    SERVER_NAME=:80

WORKDIR /app

RUN install-php-extensions \
        intl \
        pdo_pgsql \
        zip \
        opcache \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        postgresql-client \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/frankenphp/conf.d/app.dev.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
