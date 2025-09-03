# ========== 1) Node 빌드 최적화 ==========
FROM node:20-bookworm-slim AS node_build
WORKDIR /app
COPY Project/ykp-dashboard/package*.json ./
RUN npm ci --only=production --no-audit --no-fund
COPY Project/ykp-dashboard/ ./
RUN npm run build

# ========== 2) Composer 빌드 최적화 ==========  
FROM composer:2 AS composer_build
ENV COMPOSER_MEMORY_LIMIT=-1
WORKDIR /app
# vendor 캐싱을 위해 의존성 파일 먼저 복사
COPY Project/ykp-dashboard/composer.json Project/ykp-dashboard/composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress
# 소스 복사 후 autoload 재생성
COPY Project/ykp-dashboard/ ./
RUN composer dump-autoload --optimize

# ========== 3) PHP-Apache 런타임 ==========
FROM php:8.3-apache-bookworm

# 필요한 라이브러리 및 확장
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      git unzip libzip-dev libicu-dev libpq-dev \
 && docker-php-ext-configure pdo_pgsql --with-pgsql=/usr \
 && docker-php-ext-install intl zip pdo_mysql pdo_pgsql \
 && rm -rf /var/lib/apt/lists/*

# Apache 설정
RUN a2enmod rewrite
WORKDIR /var/www/html

# 앱 코드 반영
COPY Project/ykp-dashboard/ ./

# 빌드 산출물/벤더 반영
COPY --from=node_build /app/public/build ./public/build
COPY --from=composer_build /app/vendor ./vendor

# 권한 및 Laravel 초기화
RUN chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# 환경변수
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf

# 포트 (Railway가 $PORT로 주입)
EXPOSE 8080
ENV PORT=8080

# 런타임 엔트리포인트
CMD ["apache2-foreground"]