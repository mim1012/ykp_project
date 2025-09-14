FROM php:8.3-apache-bookworm
WORKDIR /var/www/html

# Apache & PHP 확장 설치
RUN a2enmod rewrite headers \
 && apt-get update \
 && apt-get install -y --no-install-recommends git unzip curl ca-certificates libicu-dev libzip-dev libpq-dev pkg-config \
 && docker-php-ext-install -j"$(nproc)" intl zip pdo_pgsql \
 && rm -rf /var/lib/apt/lists/*

# 포트 환경변수 반영 (PM 15년차 요구사항)
ARG PORT
ENV PORT=${PORT}
RUN sed -i "s/80/${PORT}/g" /etc/apache2/sites-available/000-default.conf \
 && sed -i "s/80/${PORT}/g" /etc/apache2/ports.conf \
 && sed -i "s|/var/www/html|/var/www/html/public|g" /etc/apache2/sites-available/000-default.conf \
 && echo "ServerName localhost" >> /etc/apache2/apache2.conf
EXPOSE ${PORT}

# 앱 복사 (프로젝트 루트에서 복사)
COPY . ./
RUN ls -la && pwd

# Composer & 빌드
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
 && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
 && rm composer-setup.php \
 && composer install --no-dev --optimize-autoloader \
 && composer dump-autoload -o

# 퍼미션 설정
RUN chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# 헬스체크 (PM 요구사항)
RUN printf "<?php echo 'OK';" > public/healthz.php

# Staging 환경: 마이그레이션 우회하고 웹서버만 시작
CMD php artisan config:clear \
 && php artisan route:clear \
 && php artisan view:clear \
 && php artisan cache:clear \
 && apache2-foreground