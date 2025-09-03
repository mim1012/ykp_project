# Dockerfile  
FROM php:8.2-apache-bookworm

# Apache 모듈 활성화
RUN a2enmod rewrite

# 캐시 무효화를 위한 빌드 ID
ARG BUILD_ID=2025-09-04-01

# 필요한 확장: intl, zip, pdo_mysql, pdo_pgsql  
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
    git unzip libzip-dev libicu-dev libpq-dev \
    nodejs npm \
 && docker-php-ext-install intl zip pdo_mysql pdo_pgsql \
 && rm -rf /var/lib/apt/lists/*

# 2) Composer 제공
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 3) 앱 소스 - 의존성 먼저
WORKDIR /app
COPY composer.json composer.lock package*.json ./

# 4) Composer 메모리 최적화 및 캐시 효율화
ENV COMPOSER_MEMORY_LIMIT=-1
RUN composer install --no-dev -o --no-interaction --no-progress

# 5) Node.js 의존성 설치
RUN npm ci

# 6) 전체 소스 복사
COPY . /app

# 7) Node.js 빌드
RUN npm run build

# 5) Apache 설정
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

# 6) 런타임 설정 및 권한
RUN chown -R www-data:www-data /var/www/html \
 && php artisan storage:link || true

EXPOSE 80

# 7) 스타트 스크립트로 Apache + Laravel 설정
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh
CMD ["/usr/local/bin/start.sh"]