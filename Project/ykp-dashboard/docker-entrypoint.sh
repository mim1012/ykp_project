#!/bin/bash
set -e

echo "🚀 Starting YKP Dashboard deployment..."

# .env 파일 확실히 있는지 확인
if [ ! -f .env ]; then
    echo "📝 Creating .env file from .env.example"
    cp .env.example .env
fi

# .env 파일 내용 확인
echo "📄 .env file exists: $([ -f .env ] && echo 'YES' || echo 'NO')"
ls -la .env* || true

# APP_KEY 강제 생성 (항상)
echo "🔑 Generating APP_KEY..."
php artisan key:generate --force --no-interaction
echo "✅ APP_KEY generated"

# Laravel 설정 확인
echo "🔍 Testing Laravel config..."
php artisan config:show app.name || echo "Config test failed"

# Laravel 기본 명령들 (간소화)
echo "⚡ Laravel setup..."
php artisan config:clear || true
php artisan cache:clear || true

echo "🎉 Starting Apache..."
exec apache2-foreground