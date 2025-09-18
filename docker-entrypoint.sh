#!/bin/bash
set -e

echo "🚀 Starting YKP Dashboard..."

# Run migrations if in production
if [ "$APP_ENV" = "production" ]; then
    echo "📦 Running production migrations..."
    php artisan migrate --force

    echo "🧹 Clearing caches..."
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    php artisan cache:clear

    echo "🔧 Optimizing for production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

echo "✅ Starting Apache..."
exec apache2-foreground