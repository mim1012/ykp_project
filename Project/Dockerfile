# ========== Railway OOM 해결 최적화 Dockerfile ==========

# 1) 경량 Node.js 빌드 스테이지 (메모리 절약)
FROM node:20-alpine AS frontend_build
WORKDIR /build
# 캐시 최적화: package 파일 먼저 복사
COPY Project/ykp-dashboard/package*.json ./
# 메모리 효율적인 설치
RUN npm ci --only=production --no-audit --no-fund --prefer-offline
COPY Project/ykp-dashboard/ ./
# Vite 빌드 (메모리 제한 설정)
RUN NODE_OPTIONS="--max-old-space-size=2048" npm run build

# 2) 경량 Composer 빌드 스테이지 (OOM 방지)  
FROM composer:2.7-bin AS composer_build
WORKDIR /build
# 메모리 제한 해제 + 캐시 최적화
ENV COMPOSER_MEMORY_LIMIT=-1
ENV COMPOSER_CACHE_DIR=/tmp/composer-cache
# 의존성 파일만 먼저 복사 (레이어 캐싱)
COPY Project/ykp-dashboard/composer.json Project/ykp-dashboard/composer.lock ./
# 최적화된 설치 (메모리 절약 옵션들)
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --no-plugins \
    --prefer-dist \
    --ignore-platform-reqs

# 3) 최종 런타임 스테이지 (PHP 8.3 Apache)
FROM php:8.3-apache-bookworm
WORKDIR /var/www/html

# 캐시 무효화용 빌드 ID
ARG CACHE_BUST=2025-09-04-v2

# Apache 모듈 활성화
RUN a2enmod rewrite headers

# 시스템 의존성 설치 (PostgreSQL 전용)
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
    git unzip curl ca-certificates \
    libicu-dev libzip-dev libpq-dev \
    pkg-config \
 && docker-php-ext-configure pdo_pgsql --with-pgsql=/usr \
 && docker-php-ext-install -j$(nproc) \
    intl \
    zip \
    pdo_pgsql \
 && rm -rf /var/lib/apt/lists/* \
 && apt-get clean

# Apache 설정
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf

# 애플리케이션 복사
COPY Project/ykp-dashboard/ ./

# 빌드 산출물 복사 (별도 스테이지에서)
COPY --from=frontend_build /build/public/build ./public/build
COPY --from=composer_build /build/vendor ./vendor

# Laravel 권한 설정
RUN chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# 환경변수 및 포트
ENV APP_ENV=production
ENV APP_DEBUG=false
EXPOSE 80

# 헬스체크 추가
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
  CMD curl -f http://localhost/ || exit 1

# Apache foreground 실행
CMD ["apache2-foreground"]