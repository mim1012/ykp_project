# Dockerfile
FROM php:8.2-cli-bookworm

# 1) 시스템 패키지 + Node.js + PHP 확장 빌드 의존성
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
      git unzip curl ca-certificates \
      libicu-dev g++ \
      libzip-dev zlib1g-dev \
      libpq-dev \
      nodejs npm; \
    docker-php-ext-configure intl; \
    docker-php-ext-configure zip; \
    docker-php-ext-install -j"$(nproc)" intl zip pdo_pgsql; \
    rm -rf /var/lib/apt/lists/*

# 2) Composer 제공
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 3) 앱 소스
WORKDIR /app
COPY . /app

# 4) Node.js 의존성 및 빌드
RUN set -eux; \
    npm ci; \
    npm run build

# 5) PHP 의존성
RUN set -eux; \
    composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

# 5) 런타임 설정
ENV PORT=8000
EXPOSE 8000

# 6) 엔트리포인트: 키 생성(미설정시) + 마이그레이션(실패해도 기동 유지) + 개발서버
CMD sh -lc '\
  if [ -f artisan ]; then \
    php -r "file_exists(\".env\") || copy(\".env.example\", \".env\");"; \
    php -r "echo (getenv(\"APP_KEY\")?\"APP_KEY set\\n\":\"APP_KEY missing\\n\");"; \
    php artisan key:generate --force || true; \
    php artisan migrate --force || true; \
    php artisan serve --host=0.0.0.0 --port=${PORT}; \
  else \
    echo \"artisan not found in $(pwd)\" && ls -la && sleep 3600; \
  fi'