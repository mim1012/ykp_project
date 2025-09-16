# ========== RECOMMENDED SOLUTION ==========
ARG CACHE_BUST=20250916_RECOMMENDED

# ========== Node Build Stage ==========
FROM node:20-alpine AS frontend_build
WORKDIR /app

RUN echo "✅ RECOMMENDED SOLUTION v$CACHE_BUST - NO VENDOR COPY @ $(date)" && sleep 3

COPY ykp-dashboard/package*.json ./
RUN npm ci --no-audit --no-fund --prefer-offline

COPY ykp-dashboard/ ./
ENV NODE_OPTIONS=--max-old-space-size=2048
RUN npm run build

# ========== PHP Runtime Stage ==========
FROM php:8.3-apache-bookworm
WORKDIR /var/www/html

ARG CACHE_BUST=20250916_RECOMMENDED
RUN echo "🎯 RECOMMENDED PHP STAGE v$CACHE_BUST - COMPOSER INSTALL @ $(date)" && sleep 2

# Apache modules
RUN a2enmod rewrite headers

# Install dependencies
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      git unzip curl ca-certificates \
      libicu-dev libzip-dev zlib1g-dev libpq-dev pkg-config \
 && docker-php-ext-configure pdo_pgsql --with-pgsql=/usr \
 && docker-php-ext-install -j"$(nproc)" intl zip pdo_pgsql \
 && rm -rf /var/lib/apt/lists/* \
 && apt-get clean

# Set DocumentRoot
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
      /etc/apache2/sites-available/000-default.conf

# Install Composer first
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php

# RECOMMENDED APPROACH: Copy composer files first, install, then copy app
COPY ykp-dashboard/composer.json ./
COPY ykp-dashboard/composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Copy application code AFTER vendor is installed
COPY ykp-dashboard/ ./

# Copy frontend build output
COPY --from=frontend_build /app/public/build ./public/build

# Optimize autoloader
RUN composer dump-autoload --optimize

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# Environment
ENV APP_ENV=production
ENV APP_DEBUG=false
EXPOSE 80

# Health check
RUN apt-get update && apt-get install -y --no-install-recommends curl && rm -rf /var/lib/apt/lists/*
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
  CMD curl -f http://localhost/ || exit 1

CMD ["apache2-foreground"]