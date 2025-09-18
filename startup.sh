#!/bin/bash
set -e

echo "🚀 Starting YKP Dashboard on Railway..."

# Set PORT from environment (Railway provides this)
export PORT=${PORT:-80}
echo "📡 PORT environment variable: $PORT"

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

# Configure Apache to use PORT from environment
echo "📡 Configuring Apache to listen on 0.0.0.0:${PORT}..."

# Update ports.conf with actual PORT value
echo "Listen 0.0.0.0:${PORT}" > /etc/apache2/ports.conf

# Replace PORT placeholder in VirtualHost config
sed -i "s/\${PORT}/${PORT}/g" /etc/apache2/sites-available/001-app.conf

# Ensure ServerName is set
echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Test health endpoint internally
echo "🔍 Testing health endpoint..."
php /var/www/html/public/health.php
echo ""

# Test Apache configuration
echo "🔍 Testing Apache configuration..."
apache2ctl configtest

# Show listening configuration
echo "📡 Apache will listen on:"
cat /etc/apache2/ports.conf
echo ""

# Diagnostic commands for debugging
echo "🔍 Running diagnostics..."
echo "📍 Current directory: $(pwd)"
echo "📍 health.php exists: $(test -f /var/www/html/public/health.php && echo 'YES' || echo 'NO')"
echo "📍 .htaccess exists: $(test -f /var/www/html/public/.htaccess && echo 'YES' || echo 'NO')"
echo ""

# Start Apache in foreground
echo "✅ Starting Apache on 0.0.0.0:${PORT}..."
echo "📡 Apache is binding to all network interfaces"
echo "🌐 Health check endpoint: http://0.0.0.0:${PORT}/health.php"

# Use exec to replace the shell with apache2
exec apache2-foreground