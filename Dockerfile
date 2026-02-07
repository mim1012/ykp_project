# Node Build Stage
FROM node:20-alpine AS frontend_build
WORKDIR /app

COPY package.json ./
COPY package-lock.json* ./

RUN npm ci --no-audit --no-fund || npm install --no-audit --no-fund

COPY . ./
ENV NODE_OPTIONS=--max-old-space-size=2048
RUN npm run build

# PHP Runtime Stage
FROM php:8.3-apache-bookworm
WORKDIR /var/www/html

RUN a2enmod rewrite headers \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && echo "Listen 8080" > /etc/apache2/ports.conf

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      git unzip curl ca-certificates \
      libicu-dev libzip-dev zlib1g-dev libpq-dev pkg-config \
      libpng-dev libjpeg-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" intl zip pdo pdo_pgsql gd \
 && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
      /etc/apache2/sites-available/000-default.conf \
    && echo "DirectoryIndex index.php" >> /etc/apache2/apache2.conf

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php

COPY . ./

RUN rm -rf vendor

RUN COMPOSER_ALLOW_SUPERUSER=1 composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-progress \
    --no-scripts

COPY --from=frontend_build /app/public/build ./public/build

RUN cp .env.example .env || echo "APP_KEY=" > .env

RUN php artisan package:discover --ansi || echo "Package discovery completed with warnings"

RUN mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

ENV APP_ENV=production
ENV APP_DEBUG=false
ENV PORT=8080
EXPOSE 8080

COPY apache-site.conf /etc/apache2/sites-available/000-default.conf

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

HEALTHCHECK --interval=30s --timeout=10s --start-period=120s --retries=10 \
  CMD curl -f http://localhost:${PORT:-8080}/health.txt || exit 1

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
