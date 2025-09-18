#!/bin/bash
set -e

echo "🚀 Starting YKP Dashboard..."

# Wait for database to be ready (if configured)
if [ ! -z "$DB_HOST" ]; then
    echo "⏳ Waiting for database at $DB_HOST:${DB_PORT:-5432}..."
    timeout=30
    while ! timeout 1 bash -c "echo > /dev/tcp/$DB_HOST/${DB_PORT:-5432}" 2>/dev/null && [ $timeout -gt 0 ]; do
        sleep 1
        ((timeout--))
        echo "Waiting... ($timeout seconds left)"
    done

    if [ $timeout -eq 0 ]; then
        echo "⚠️ Database connection timeout, continuing anyway..."
    else
        echo "✅ Database is ready!"
    fi
fi

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