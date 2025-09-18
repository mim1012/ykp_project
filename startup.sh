#!/bin/bash
set -e

echo "🚀 Starting YKP Dashboard on port 80..."

# Sync environment variables to .env if needed
if [ -n "$APP_KEY" ]; then
    echo "APP_KEY=$APP_KEY" >> .env
fi
if [ -n "$DB_HOST" ]; then
    echo "DB_CONNECTION=${DB_CONNECTION:-pgsql}" >> .env
    echo "DB_HOST=$DB_HOST" >> .env
    echo "DB_PORT=${DB_PORT:-5432}" >> .env
    echo "DB_DATABASE=$DB_DATABASE" >> .env
    echo "DB_USERNAME=$DB_USERNAME" >> .env
    echo "DB_PASSWORD=$DB_PASSWORD" >> .env
fi

# Generate app key if not set
if ! grep -q "^APP_KEY=.\+" .env 2>/dev/null; then
    echo "🔑 Generating application key..."
    php artisan key:generate --force || true
fi

# Fix permissions
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Test Apache configuration
echo "🔍 Testing Apache configuration..."
apache2ctl configtest

echo "✅ Starting Apache on port 80..."
exec apache2-foreground