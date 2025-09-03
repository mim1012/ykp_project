# ===== 1) Frontend build (Node) =====
FROM node:20-alpine AS frontend_build
WORKDIR /build

COPY Project/ykp-dashboard/package*.json ./
RUN npm ci --include=dev --no-audit --no-fund --prefer-offline

COPY Project/ykp-dashboard/ ./
ENV NODE_OPTIONS="--max-old-space-size=2048"
RUN npm run build

# ===== 2) Composer install =====
FROM composer:2 AS composer_build
WORKDIR /build

ENV COMPOSER_MEMORY_LIMIT=-1 \
    COMPOSER_MAX_PARALLEL_HTTP=3 \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    COMPOSER_PROCESS_TIMEOUT=1200

# 캐시 최적화: 의존성부터
COPY Project/ykp-dashboard/composer.json Project/ykp-dashboard/composer.lock ./

# ⚠️ 가능하면 --ignore-platform-reqs / --no-plugins 제거 권장
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

# 전체 소스가 필요하면 여기서 복사해도 되지만
# 최종 런타임 스테이지에서 앱 소스를 복사하므로 생략

# ===== 3) PHP 8.3 Apache runtime =====
FROM php:8.3-apache-bookworm
WORKDIR /var/www/html

ARG CACHE_BUST=2025-09-04-02
RUN echo ">>> ROOT DOCKERFILE ${CACHE_BUST}"

# Apache + PHP 확장
RUN a2enmod rewrite headers \
 && apt-get update \
 && apt-get install -y --no-install-recommends \
      git unzip curl ca-certificates \
      libicu-dev libzip-dev libpq-dev pkg-config \
 && docker-php-ext-install -j"$(nproc)" intl zip pdo_pgsql \
 && rm -rf /var/lib/apt/lists/* \
 && apt-get clean

# DocumentRoot 및 .htaccess 활성화(매우 중요)
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf \
 && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

# 앱 소스 복사
COPY Project/ykp-dashboard/ ./

# 빌드 산출물/벤더 주입
COPY --from=frontend_build  /build/public/build ./public/build
COPY --from=composer_build /build/vendor ./vendor

# 권한
RUN chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage/bootstrap/cache

# 심플 헬스엔드포인트 추가(리포지토리에 파일로 포함해도 됨)
RUN printf "<?php echo 'OK';" > public/healthz.php

EXPOSE 80

# 헬스체크를 정적 엔드포인트로
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
  CMD curl -fsS http://localhost/healthz.php || exit 1

# 첫 부팅 안정화(캐시 비우기 정도만)
CMD bash -lc "php artisan config:clear || true; php artisan route:clear || true; php artisan view:clear || true; apache2-foreground"
