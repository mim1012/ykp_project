# ===== 1) Frontend build (Node) =====
FROM node:20-bullseye-slim AS frontend_build
WORKDIR /build

ENV npm_config_loglevel=warn \
    npm_config_progress=false \
    npm_config_fetch_retries=5 \
    npm_config_maxsockets=1
ENV NODE_OPTIONS="--max-old-space-size=2048"

COPY Project/ykp-dashboard/package*.json ./
RUN npm ci --no-audit --no-fund --prefer-offline --cache /tmp/npm-cache --legacy-peer-deps

COPY Project/ykp-dashboard/ ./
RUN npm run build

# ===== 2) PHP 8.3 Apache runtime =====
FROM php:8.3-apache-bookworm
WORKDIR /var/www/html

# PHP 확장 + Apache 모듈
RUN a2enmod rewrite headers \
 && apt-get update \
 && apt-get install -y --no-install-recommends \
      git unzip curl ca-certificates \
      libicu-dev libzip-dev libpq-dev pkg-config \
 && docker-php-ext-install -j"$(nproc)" intl zip pdo_pgsql \
 && rm -rf /var/lib/apt/lists/* \
 && apt-get clean

# Apache DocumentRoot + .htaccess 활성화
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf \
 && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

# Install Composer first
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
 && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
 && rm composer-setup.php

# RECOMMENDED APPROACH: Copy composer files first, install, then copy app
COPY Project/ykp-dashboard/composer.json ./
COPY Project/ykp-dashboard/composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Copy application code AFTER vendor is installed
COPY Project/ykp-dashboard/ ./

# 프론트 빌드 산출물 복사
COPY --from=frontend_build /build/public/build ./public/build

# autoload 최적화
RUN composer dump-autoload -o

# 권한
RUN chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# 단순 헬스엔드포인트
RUN printf "<?php echo 'OK';" > public/healthz.php

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
  CMD curl -fsS http://localhost/healthz.php || exit 1

CMD bash -lc "php artisan config:clear || true; php artisan route:clear || true; php artisan view:clear || true; apache2-foreground"