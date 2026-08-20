FROM node:22-bookworm-slim AS frontend

WORKDIR /src/frontend
COPY frontend/package.json frontend/package-lock.json* ./
RUN npm ci --ignore-scripts || npm install --ignore-scripts
COPY frontend/ ./
COPY backend/public-php /src/backend/public-php
RUN mkdir -p /src/backend/public && npx svelte-kit sync && npm run build

FROM php:8.3-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        libonig-dev \
        libsodium-dev \
        libsqlite3-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
        zlib1g-dev \
    && docker-php-ext-install -j$(nproc) \
        mbstring \
        opcache \
        pdo \
        pdo_sqlite \
        sodium \
        zip \
    && if ! php -m | grep -qi sqlite3; then docker-php-ext-install sqlite3; fi \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php.ini /usr/local/etc/php/conf.d/cimtapp.ini
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/cimtapp-entrypoint
RUN chmod +x /usr/local/bin/cimtapp-entrypoint

WORKDIR /var/www/cimtapp/backend

COPY backend /var/www/cimtapp/backend
COPY --from=frontend /src/backend/public /var/www/cimtapp/backend/public
COPY backend/public-php/ /var/www/cimtapp/backend/public/

RUN composer install --no-interaction --prefer-dist \
    && mkdir -p /var/www/cimtapp/data /var/www/cimtapp/backend/logs /var/www/cimtapp/backend/var/cache \
    && chown -R www-data:www-data /var/www/cimtapp

ENV docker=true

EXPOSE 80

HEALTHCHECK --interval=10s --timeout=3s --start-period=20s --retries=5 \
    CMD curl -fsS http://127.0.0.1/api/v1/health || exit 1

ENTRYPOINT ["cimtapp-entrypoint"]
CMD ["apache2-foreground"]
