# ===== 1) Frontend build (Node) =====
FROM node:20-bullseye-slim AS frontend_build
WORKDIR /build

ENV npm_config_loglevel=warn \
    npm_config_progress=false \
    npm_config_fetch_retries=5 \
    npm_config_maxsockets=1
ENV NODE_OPTIONS="--max-old-space-size=2048"

COPY Project/ykp-dashboard/package*.json ./

# 🔧 핵심: --omit=optional 제거 (rollup 네이티브 패키지 필요)
RUN npm ci --no-audit --no-fund --prefer-offline --cache /tmp/npm-cache --legacy-peer-deps

COPY Project/ykp-dashboard/ ./
RUN npm run build


# ===== 2) Composer install =====
FROM php:8.3-cli-bookworm AS composer_build
WORKDIR /build

# 런타임과 동일 확장 설치 (intl 필수)
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      git unzip libicu-dev libzip-dev libpq-dev pkg-config \
 && docker-php-ext-install -j"$(nproc)" intl zip pdo_pgsql \
 && rm -rf /var/lib/apt/lists/*

# Composer 설치
ENV COMPOSER_MEMORY_LIMIT=-1 \
    COMPOSER_MAX_PARALLEL_HTTP=3 \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    COMPOSER_PROCESS_TIMEOUT=1200 \
    COMPOSER_ALLOW_SUPERUSER=1
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 의존성 먼저 설치(캐시 최적화)
COPY Project/ykp-dashboard/composer.json Project/ykp-dashboard/composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

# ===== 3) PHP 8.3 Apache runtime =====
FROM php:8.3-apache-bookworm
WORKDIR /var/www/html

ARG CACHE_BUST=2025-09-04-03
RUN echo ">>> ROOT DOCKERFILE ${CACHE_BUST}"

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

# 앱 소스 복사
COPY Project/ykp-dashboard/ ./

# 빌드 산출물/벤더 복사
COPY --from=frontend_build  /build/public/build ./public/build
COPY --from=composer_build /build/vendor ./vendor

# 권한 설정
RUN chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# 단순 헬스체크 엔드포인트
RUN printf "<?php echo 'OK';" > public/healthz.php

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
  CMD curl -fsS http://localhost/healthz.php || exit 1

CMD bash -lc "php artisan config:clear || true; php artisan route:clear || true; php artisan view:clear || true; apache2-foreground"
