# ================================
# Stage 1: Frontend Build
# ================================
FROM node:20-bullseye-slim AS frontend-builder
WORKDIR /app

# 프론트 패키지 파일만 먼저 복사 (캐시 최적화)
COPY package*.json ./
ENV NODE_OPTIONS=--max-old-space-size=2048
ENV npm_config_maxsockets=1
ENV CI=true
RUN npm ci --no-audit --no-fund

# 소스 복사 후 빌드
COPY . ./
RUN npm run build

# ================================
# Stage 2: Runtime (PHP + Apache)
# ================================
FROM php:8.3-apache-bookworm AS runtime
WORKDIR /var/www/html

# Apache 모듈
RUN a2enmod rewrite headers

# PHP 확장
RUN apt-get update && apt-get install -y --no-install-recommends \
    libicu-dev libzip-dev libpq-dev unzip curl ca-certificates \
  && docker-php-ext-install -j"$(nproc)" intl zip pdo_pgsql \
  && rm -rf /var/lib/apt/lists/*

# Composer 바이너리만 복사(외부 이미지에서 직접 복사)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# DocumentRoot & AllowOverride
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

# 앱 구성요소 복사
# 1) Composer 메타
COPY composer.json composer.lock ./
# 2) 로컬에서 미리 만든 vendor 복사 (vendor COPY 전략)
COPY vendor/ ./vendor/
# 3) 앱 전체 소스
COPY . ./

# 프론트 빌드 산출물 반영
COPY --from=frontend-builder /app/public/build ./public/build

# .env 파일 확실히 생성 (빌드 시점) - Railway용
RUN cp .env.railway .env

# entrypoint 스크립트 복사 및 권한 설정
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# 권한
RUN chown -R www-data:www-data storage bootstrap/cache && chmod -R 775 storage bootstrap/cache

# 오토로드 최적화 및 Laravel 캐시 클리어
RUN composer dump-autoload -o && \
    php artisan config:clear && \
    php artisan cache:clear && \
    php artisan route:clear && \
    php artisan view:clear

# 헬스체크(정적 엔드포인트)
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 CMD curl -fsS http://localhost/healthz.php || exit 1

EXPOSE 80

# entrypoint 스크립트 사용
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]