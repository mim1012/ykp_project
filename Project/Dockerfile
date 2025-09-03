# ========== 1) Node 빌드 ==========
FROM node:18-bookworm AS node_build
WORKDIR /app
COPY Project/ykp-dashboard/package*.json ./
RUN npm ci
COPY Project/ykp-dashboard/ ./
# 프론트 빌드 (Vite)
RUN npm run build

# ========== 2) Composer & PHP 확장(빌드종속) ==========
FROM composer:2 AS composer_build
WORKDIR /app
COPY Project/ykp-dashboard/composer.json Project/ykp-dashboard/composer.lock ./
RUN composer install --no-dev -o --no-interaction --no-progress

# ========== 3) PHP-Apache 런타임 ==========
FROM php:8.3-apache-bookworm

# 필요한 라이브러리 및 확장
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      git unzip libzip-dev libicu-dev libpq-dev zlib1g-dev \
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