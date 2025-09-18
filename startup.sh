#!/bin/bash
set -e

echo "🚀 Starting YKP Dashboard on port 80..."

# Create .env if not exists
if [ ! -f .env ]; then
    cp .env.example .env 2>/dev/null || echo "APP_KEY=" > .env
fi

# Sync environment variables to .env if needed
if [ -n "$APP_KEY" ]; then
    sed -i "s/^APP_KEY=.*/APP_KEY=$APP_KEY/" .env
fi
if [ -n "$DB_HOST" ]; then
    {
        echo "DB_CONNECTION=${DB_CONNECTION:-pgsql}"
        echo "DB_HOST=$DB_HOST"
        echo "DB_PORT=${DB_PORT:-5432}"
        echo "DB_DATABASE=$DB_DATABASE"
        echo "DB_USERNAME=$DB_USERNAME"
        echo "DB_PASSWORD=$DB_PASSWORD"
    } >> .env
fi

# Fix permissions
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Test that health.php works
echo "🔍 Testing health endpoint..."
php /var/www/html/public/health.php
echo ""

# Test Apache configuration
echo "🔍 Testing Apache configuration..."
apache2ctl configtest

# Ensure Apache listens on all interfaces on port 80
echo "📡 Configuring Apache to listen on 0.0.0.0:80..."
echo "Listen 0.0.0.0:80" > /etc/apache2/ports.conf
echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Verify listening configuration
echo "🔍 Verifying Apache will listen on:"
cat /etc/apache2/ports.conf

echo "✅ Starting Apache on 0.0.0.0:80..."
echo "📡 Apache will bind to all network interfaces"
exec apache2-foreground