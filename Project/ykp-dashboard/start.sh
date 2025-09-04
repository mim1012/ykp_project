#!/usr/bin/env bash
set -euo pipefail

echo "🚀 Starting YKP ERP with Railway Variables..."

# Default working dir inside container image
cd "${APP_WORKDIR:-/var/www/html}" || cd /var/www/html

# 이미지에 .env가 들어있으면 제거 (Railway 변수를 우선 사용)
[ -f .env ] && rm -f .env && echo "📝 Removed embedded .env file"

# 부팅 로그로 현재 DB 설정 확인
echo "🔍 Checking Railway Variables..."
php -r "echo 'DB_HOST='.getenv('DB_HOST').PHP_EOL;"
php -r "echo 'DB_USERNAME='.getenv('DB_USERNAME').PHP_EOL;"
php -r "echo 'DB_DATABASE='.getenv('DB_DATABASE').PHP_EOL;"

PORT="${PORT:-8080}"

# Configure Apache to listen on $PORT
if [ -f /etc/apache2/ports.conf ]; then
  sed -ri "s/^Listen [0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf || true
  if ! grep -q "Listen ${PORT}" /etc/apache2/ports.conf; then
    echo "Listen ${PORT}" >> /etc/apache2/ports.conf
  fi
fi

if [ -f /etc/apache2/sites-enabled/000-default.conf ]; then
  sed -ri "s#<VirtualHost \*:[0-9]+>#<VirtualHost *:${PORT}>#" /etc/apache2/sites-enabled/000-default.conf || true
fi

# Laravel optimizations & migrations
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Run migrations in production containers
php artisan migrate --force || true

exec apache2-foreground

