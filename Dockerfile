# ---------- Node 빌드 ----------
FROM node:20-bullseye AS node_build
WORKDIR /app
COPY Project/ykp-dashboard/package*.json ./
RUN npm ci
COPY Project/ykp-dashboard/ .
RUN npm run build

# ---------- Composer ----------
FROM composer:2 AS composer_build
WORKDIR /app
COPY Project/ykp-dashboard/composer.json Project/ykp-dashboard/composer.lock ./
RUN composer install --no-dev -o --no-interaction --no-progress
COPY Project/ykp-dashboard/ .
RUN composer install --no-dev -o --no-interaction --no-progress

# ---------- 런타임 (Apache + PHP 8.3) ----------
FROM php:8.3-apache
RUN apt-get update && apt-get install -y git unzip libzip-dev \
    && docker-php-ext-install zip pdo_mysql pdo_pgsql
RUN a2enmod rewrite

WORKDIR /var/www/html
COPY --from=composer_build /app /var/www/html
COPY --from=node_build /app/public /var/www/html/public

RUN chown -R www-data:www-data storage bootstrap/cache
RUN php artisan storage:link || true

COPY Project/ykp-dashboard/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

EXPOSE 8080
CMD ["/usr/local/bin/start.sh"]
