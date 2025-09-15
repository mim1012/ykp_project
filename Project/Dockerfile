# ========== 1) Node 빌드 (Vite) ==========
# devDependencies(=vite 등) 필요하므로 production-only 설치 금지!
FROM node:20-alpine AS frontend_build
WORKDIR /app

# 확인용 - Railway가 루트 Dockerfile 사용 중임을 검증
RUN echo ">>> USING ROOT DOCKERFILE @ $(date)" && sleep 2

# 레이어 캐시 최적화
COPY Project/ykp-dashboard/package*.json ./
RUN npm ci --no-audit --no-fund --prefer-offline

COPY Project/ykp-dashboard/ ./
# Vite 빌드 시 메모리 상한(2GB) — Railway OOM 예방
ENV NODE_OPTIONS=--max-old-space-size=2048
RUN npm run build

# ========== 2) Composer 빌드 ==========
# composer 이미지만 쓰고 PHP 확장은 무시 — vendor만 뽑아옴
FROM composer:2 AS composer_build
WORKDIR /app

# 메모리 제한 해제 + 캐시
ENV COMPOSER_MEMORY_LIMIT=-1
ENV COMPOSER_CACHE_DIR=/tmp/composer-cache

COPY Project/ykp-dashboard/composer.json Project/ykp-dashboard/composer.lock ./

# OOM/플랫폼 이슈 완화 옵션들
# (scripts를 막으면 package:discover가 안 돌 수 있으니 여기서는 허용)
RUN composer install \
  --no-dev \
  --optimize-autoloader \
  --no-interaction \
  --no-progress \
  --prefer-dist \
  --ignore-platform-reqs

# ========== 3) PHP 8.3 + Apache 런타임 ==========
FROM php:8.3-apache-bookworm
WORKDIR /var/www/html

# 캐시 버스터(선택)
ARG CACHE_BUST=2025-09-04-v5

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

# 앱 소스
COPY Project/ykp-dashboard/ ./

# 빌드 산출물 반영
COPY --from=frontend_build /app/public/build ./public/build

# Composer 의존성 반영 (빌드 스테이지에서)
COPY --from=composer_build /app/vendor ./vendor

# Composer autoload 최적화 (의존성 설치 완료 후)
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