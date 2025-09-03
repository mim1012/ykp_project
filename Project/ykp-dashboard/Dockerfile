# Multi-stage Dockerfile for Laravel + Vite (monorepo path: Project/ykp-dashboard)

FROM node:18 AS frontend
WORKDIR /app
COPY . ./
RUN ls -la resources/js/ && cat vite.config.js | grep -A 10 "input:"
RUN npm ci && npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --ignore-platform-reqs

FROM php:8.2-apache
WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y git unzip libpq-dev libzip-dev libicu-dev postgresql-client \
    && docker-php-ext-install intl zip pdo_mysql pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Apache document root to /public and enable rewrite
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && a2enmod rewrite

# Copy application code
COPY . ./
# Copy vendor and built assets
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build

# Startup script to set Apache port and warm caches+migrations
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Permissions for storage/cache
RUN chown -R www-data:www-data storage bootstrap/cache || true

ENV APP_ENV=production \
    APP_DEBUG=false

EXPOSE 80

# Default start (migrations can be run by start command in Railway)
CMD ["start.sh"]