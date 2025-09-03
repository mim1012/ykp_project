# ===== 1) Frontend build (Node) =====
FROM node:20-alpine AS frontend_build
WORKDIR /build

# package 파일 복사
COPY Project/ykp-dashboard/package*.json ./

# devDependencies 포함 설치
RUN npm ci --include=dev --no-audit --no-fund --prefer-offline

# 앱 코드 복사
COPY Project/ykp-dashboard/ ./

# Vite 빌드 (메모리 제한 2GB)
ENV NODE_OPTIONS="--max-old-space-size=2048"
RUN npm run build

# ===== 2) Composer install =====
FROM composer:2 AS composer_build
WORKDIR /build

# 메모리/병렬 제한 및 캐시 경로
ENV COMPOSER_MEMORY_LIMIT=-1 \
    COMPOSER_MAX_PARALLEL_HTTP=3 \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    COMPOSER_PROCESS_TIMEOUT=1200

# 의존성 파일만 먼저 복사
COPY Project/ykp-dashboard/composer.json Project/ykp-dashboard/composer.lock ./

# 네트워크/메모리 부담 최소화 옵션 세트
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --no-plugins \
    --optimize-autoloader \
    --classmap-authoritative \
    --apcu-autoloader \
    --ignore-platform-reqs

# ===== 3) PHP 8.3 Apache runtime =====
FROM php:8.3-apache-bookworm
WORKDIR /var/www/html

# 캐시버스터(값만 바꿔 커밋하면 강제 재빌드)
ARG CACHE_BUST=2025-09-04-02
RUN echo ">>> ROOT DOCKERFILE ${CACHE_BUST}"

# Apache
RUN a2enmod rewrite headers

# 확장 빌드에 필요한 시스템 패키지
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      git unzip curl ca-certificates \
      libicu-dev libzip-dev libpq-dev \
      pkg-config \
 && docker-php-ext-install -j"$(nproc)" intl zip pdo_pgsql \
 && rm -rf /var/lib/apt/lists/* \
 && apt-get clean

# DocumentRoot 변경
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf

# 앱 소스
COPY Project/ykp-dashboard/ ./

# 빌드 산출물/벤더 주입
COPY --from=frontend_build  /build/public/build ./public/build
COPY --from=composer_build /build/vendor ./vendor

# 권한
RUN chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

ENV APP_ENV=production
ENV APP_DEBUG=false
EXPOSE 80

HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
  CMD curl -f http://localhost/ || exit 1

CMD ["apache2-foreground"]
