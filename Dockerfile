# ---------- 1) Node 빌드 ----------
FROM node:20-bookworm-slim AS node_build
WORKDIR /app
COPY Project/ykp-dashboard/package*.json ./
RUN npm ci
COPY Project/ykp-dashboard/ .
RUN npm run build

# ---------- 2) Composer (PHP CLI + intl 설치) ----------
FROM php:8.3-cli-bookworm AS composer_build
# ext-intl에 필요한 라이브러리
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libzip-dev libicu-dev \
 && docker-php-ext-install intl zip \
 && rm -rf /var/lib/apt/lists/*

# composer 바이너리 설치
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY Project/ykp-dashboard/composer.json Project/ykp-dashboard/composer.lock ./
RUN composer install --no-dev -o --no-interaction --no-progress
COPY Project/ykp-dashboard/ .
RUN composer install --no-dev -o --no-interaction --no-progress

# ---------- 3) 런타임 (Apache + PHP 8.3) ----------
FROM php:8.3-apache-bookworm
# 필요한 확장: intl, zip, pdo_mysql, pdo_pgsql
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libzip-dev libicu-dev \
 && docker-php-ext-install intl zip pdo_mysql pdo_pgsql \
 && rm -rf /var/lib/apt/lists/*
RUN a2enmod rewrite

WORKDIR /var/www/html
# PHP 소스 + vendor
COPY --from=composer_build /app /var/www/html
# 프런트 빌드 산출물
COPY --from=node_build /app/public /var/www/html/public

# 권한 및 라라벨 준비
RUN chown -R www-data:www-data storage bootstrap/cache \
 && php artisan storage:link || true

# 시작 스크립트
COPY Project/ykp-dashboard/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

EXPOSE 8080
CMD ["/usr/local/bin/start.sh"]
