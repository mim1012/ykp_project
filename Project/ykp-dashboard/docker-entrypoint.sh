#!/bin/bash
set -e

echo "🚀 Starting YKP Dashboard deployment..."

# .env 파일 확실히 생성
if [ ! -f .env ]; then
    echo "📝 Creating .env file from .env.example"
    cp .env.example .env
    echo "✅ .env file created"
else
    echo "✅ .env file already exists"
fi

# APP_KEY 확인 및 생성
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    echo "🔑 Generating APP_KEY..."
    php artisan key:generate --force
    echo "✅ APP_KEY generated"
else
    echo "✅ APP_KEY already set"
fi

# Laravel 캐시 및 최적화 (에러 무시)
echo "⚡ Optimizing Laravel..."
php artisan config:cache || echo "Config cache failed, continuing..."
php artisan route:cache || echo "Route cache failed, continuing..."
php artisan view:cache || echo "View cache failed, continuing..."

# 마이그레이션 시도 (에러 무시)
echo "🗄️ Running migrations..."
php artisan migrate --force || echo "Migration failed, continuing..."

echo "🎉 Laravel setup complete, starting Apache..."

# Apache 실행
exec apache2-foreground