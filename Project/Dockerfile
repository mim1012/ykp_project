# ========== CACHE BUST - 숫자 변경 시 완전 새 빌드 ==========
ARG CACHE_BUST=3

# ========== 1) Node 빌드 (Vite) ==========
# devDependencies(=vite 등) 필요하므로 production-only 설치 금지!
FROM node:20-alpine AS frontend_build
WORKDIR /app

# 확인용 - Railway가 루트 Dockerfile 사용 중임을 검증
RUN echo ">>> USING ROOT DOCKERFILE v3 @ $(date)" && sleep 2

# 레이어 캐시 최적화
COPY Project/ykp-dashboard/package*.json ./
RUN npm ci --no-audit --no-fund --prefer-offline

COPY Project/ykp-dashboard/ ./
# Vite 빌드 시 메모리 상한(2GB) — Railway OOM 예방
ENV NODE_OPTIONS=--max-old-space-size=2048
RUN npm run build

# ========== 2) Composer 빌드 스테이지 제거 ==========
# PHP 런타임에서 직접 설치하도록 통합

# ========== 3) PHP 8.3 + Apache 런타임 ==========
FROM php:8.3-apache-bookworm
WORKDIR /var/www/html

# 캐시 버스터 강화 - Railway 완전 새 빌드 강제
ARG CACHE_BUST=3
RUN echo ">>> CACHE BUST v$CACHE_BUST - NO VENDOR COPY @ $(date)" && sleep 1

# Apache 모듈
RUN a2enmod rewrite headers

# 런타임 의존성 (Postgres용)
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      git unzip curl ca-certificates \
      libicu-dev libzip-dev zlib1g-dev libpq-dev pkg-config \
 && docker-php-ext-configure pdo_pgsql --with-pgsql=/usr \
 && docker-php-ext-install -j"$(nproc)" intl zip pdo_pgsql \
 && rm -rf /var/lib/apt/lists/* \
 && apt-get clean

# DocumentRoot 변경
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
      /etc/apache2/sites-available/000-default.conf

# Composer 의존성 파일 먼저 복사 (레이어 캐시 최적화)
COPY Project/ykp-dashboard/composer.json Project/ykp-dashboard/composer.lock ./

# Composer 설치 및 의존성 설치
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php \
    && composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# 앱 소스 복사 (vendor는 이미 설치됨)
COPY Project/ykp-dashboard/ ./

# 빌드 산출물 반영
COPY --from=frontend_build /app/public/build ./public/build

# Composer autoload 최종 최적화
RUN composer dump-autoload --optimize

# 권한
RUN chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# 환경
ENV APP_ENV=production
ENV APP_DEBUG=false
EXPOSE 80

# 헬스체크
RUN apt-get update && apt-get install -y --no-install-recommends curl && rm -rf /var/lib/apt/lists/*
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
  CMD curl -f http://localhost/ || exit 1

CMD ["apache2-foreground"]