#!/bin/bash
set -e

echo "🚀 Starting YKP Dashboard deployment..."

# Railway 환경변수를 .env 파일에 직접 쓰기
echo "📝 Creating .env from Railway environment variables..."
cat > .env << EOF
# Railway Environment Variables
APP_NAME="YKP ERP"
APP_ENV=${APP_ENV:-production}
APP_DEBUG=${APP_DEBUG:-false}
APP_KEY=${APP_KEY}
APP_URL=https://ykpproject-production.up.railway.app

# Database
DB_CONNECTION=${DB_CONNECTION:-pgsql}
DATABASE_URL="${DATABASE_URL}"

# Laravel Settings
LOG_CHANNEL=stack
LOG_LEVEL=error
CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync

# Locale
APP_LOCALE=ko
APP_FALLBACK_LOCALE=en
EOF

echo "✅ .env file created from Railway environment"
echo "📄 .env file size: $(wc -c < .env) bytes"

# APP_KEY 확인 및 생성
if [ -z "$APP_KEY" ]; then
    echo "🔑 Generating APP_KEY..."
    php artisan key:generate --force --no-interaction
else
    echo "✅ APP_KEY already set from Railway"
fi

# Laravel 기본 설정
echo "⚡ Laravel setup..."
php artisan config:clear || true
php artisan cache:clear || true

echo "🎉 Starting Apache..."
exec apache2-foreground