#!/bin/bash
# Don't use set -e to allow graceful failures

echo "🚀 Starting YKP Dashboard..."

# Skip database check to speed up startup
echo "📝 Environment: ${APP_ENV:-production}"

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ]; then
    echo "🔑 Generating application key..."
    php artisan key:generate --force || echo "⚠️ Key generation failed, continuing..."
fi

# Clear any problematic caches first
echo "🧹 Clearing old caches..."
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan cache:clear || true

# Run migrations
echo "📦 Running migrations..."
php artisan migrate --force || echo "⚠️ Migrations failed or already run"

# Optimize for production
if [ "$APP_ENV" = "production" ]; then
    echo "🔧 Optimizing for production..."
    php artisan config:cache || echo "⚠️ Config cache failed"
    php artisan route:cache || echo "⚠️ Route cache failed"
    php artisan view:cache || echo "⚠️ View cache failed"
fi

# Create storage link
echo "🔗 Creating storage link..."
php artisan storage:link || echo "Storage link already exists"

echo "✅ Starting Apache..."
exec apache2-foreground