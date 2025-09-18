# ========== FINAL SOLUTION ==========
ARG FINAL_SOLUTION=20250916_SUCCESS

# ========== Node Build Stage ==========
FROM node:20-alpine AS frontend_build
WORKDIR /app

RUN echo "🎯 FINAL SOLUTION v$FINAL_SOLUTION - SUCCESS @ $(date)" && sleep 3

# Copy package files
COPY package.json ./
COPY package-lock.json* ./

# Try npm ci first, fallback to npm install if it fails
RUN npm ci --no-audit --no-fund || (echo "npm ci failed, using npm install..." && npm install --no-audit --no-fund)

COPY . ./
ENV NODE_OPTIONS=--max-old-space-size=2048
RUN npm run build

# ========== PHP Runtime Stage ==========
FROM php:8.3-apache-bookworm
WORKDIR /var/www/html

ARG FINAL_SOLUTION=20250916_SUCCESS
RUN echo "✅ FINAL PHP STAGE v$FINAL_SOLUTION - NO VENDOR COPY @ $(date)" && sleep 2

# Apache modules and PHP configuration for Railway
RUN a2enmod rewrite headers \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && echo "Listen 8080" > /etc/apache2/ports.conf

# Install dependencies
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      git unzip curl ca-certificates \
      libicu-dev libzip-dev zlib1g-dev libpq-dev pkg-config \
 && docker-php-ext-install -j"$(nproc)" intl zip pdo pdo_pgsql \
 && rm -rf /var/lib/apt/lists/* \
 && apt-get clean

# Set DocumentRoot and use index-debug.php for debugging
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
      /etc/apache2/sites-available/000-default.conf \
    && echo "DirectoryIndex index-debug.php index.php" >> /etc/apache2/apache2.conf

# Install Composer first
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php

# Copy application code first
COPY . ./

# Install composer dependencies with clean autoload
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --ignore-platform-reqs

# Fix Filament autoload issue for production (run twice to ensure fix)
RUN php fix-filament-autoload.php \
    && php fix-filament-autoload.php

# Do NOT regenerate autoload as it will restore the problem
# RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# Copy frontend build output
COPY --from=frontend_build /app/public/build ./public/build

# Create .env file with basic configuration
RUN cp .env.example .env || echo "APP_KEY=" > .env

# Run package discovery safely
RUN php artisan package:discover --ansi || echo "Package discovery completed with warnings"

# Create necessary directories and set permissions
RUN mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# Environment
ENV APP_ENV=production
ENV APP_DEBUG=false

# Railway uses dynamic PORT, default to 8080
ENV PORT=8080
EXPOSE 8080

# Copy Apache configuration
COPY apache-site.conf /etc/apache2/sites-available/000-default.conf

# Copy entrypoint script
COPY docker-entrypoint-simple.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Install curl for health check
RUN apt-get update && apt-get install -y --no-install-recommends curl && rm -rf /var/lib/apt/lists/*

# Simple health check using curl with dynamic port
HEALTHCHECK --interval=30s --timeout=10s --start-period=120s --retries=10 \
  CMD curl -f http://localhost:${PORT:-8080}/health.php || exit 1

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]