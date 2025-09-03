# ---------- 1) Node 빌드 ----------
FROM node:20-bookworm-slim AS node_build
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# ---------- 2) Composer ----------  
FROM php:8.3-cli-bookworm AS composer_build
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev -o --no-interaction --no-progress
COPY . .
RUN composer install --no-dev -o --no-interaction --no-progress

# ---------- 3) 런타임 (Apache + PHP 8.3) ----------
FROM php:8.3-apache-bookworm

# Apache 설정
RUN a2enmod rewrite

# Supabase(PostgreSQL)용 필수 패키지 + PHP 확장 빌드
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      ca-certificates git unzip libzip-dev libicu-dev libpq-dev \
 && docker-php-ext-install -j"$(nproc)" intl zip pdo_pgsql \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# 애플리케이션 복사
COPY --from=composer_build /app /var/www/html
COPY --from=node_build /app/public /var/www/html/public

# 권한 및 심볼릭 링크  
RUN chown -R www-data:www-data storage bootstrap/cache \
 && php artisan storage:link || true

# 스타트 스크립트
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh
EXPOSE 8080
CMD ["/usr/local/bin/start.sh"]