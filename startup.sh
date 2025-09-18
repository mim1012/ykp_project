#!/bin/bash
set -e

echo "🚀 Starting YKP Dashboard on Railway..."
echo "================================================"

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
echo ""
echo "📡 Configuring Apache to listen on 0.0.0.0:${PORT}..."

# Update ports.conf with actual PORT value
echo "Listen 0.0.0.0:${PORT}" > /etc/apache2/ports.conf

# Replace PORT placeholder in VirtualHost config
sed -i "s/\${PORT}/${PORT}/g" /etc/apache2/sites-available/001-app.conf

# Ensure ServerName is set
grep -q "ServerName" /etc/apache2/apache2.conf || echo "ServerName localhost" >> /etc/apache2/apache2.conf

# CRITICAL DIAGNOSTICS
echo ""
echo "🔍 === APACHE CONFIGURATION DIAGNOSTICS ==="
echo ""
echo "---- apache2ctl -S (VirtualHost Configuration) ----"
apache2ctl -S || true
echo ""
echo "---- DocumentRoot search in configs ----"
grep -R "DocumentRoot" /etc/apache2/sites-available /etc/apache2/apache2.conf 2>/dev/null | head -10
echo ""
echo "---- Active sites in sites-enabled ----"
ls -la /etc/apache2/sites-enabled/
echo ""
echo "---- VirtualHost config (001-app.conf) ----"
cat /etc/apache2/sites-available/001-app.conf | head -30
echo ""
echo "---- ports.conf ----"
cat /etc/apache2/ports.conf
echo ""
echo "---- File existence check ----"
echo "📍 Current directory: $(pwd)"
echo "📍 health.php exists: $(test -f /var/www/html/public/health.php && echo 'YES ✅' || echo 'NO ❌')"
echo "📍 .htaccess exists: $(test -f /var/www/html/public/.htaccess && echo 'YES ✅' || echo 'NO ❌')"
echo "📍 index.php exists: $(test -f /var/www/html/public/index.php && echo 'YES ✅' || echo 'NO ❌')"
echo ""

# Test health endpoint internally with PHP CLI
echo "🔍 Testing health.php with PHP CLI..."
php /var/www/html/public/health.php && echo " ✅ PHP CLI test passed" || echo " ❌ PHP CLI test failed"
echo ""

# Test Apache configuration
echo "🔍 Testing Apache configuration..."
apache2ctl configtest

# Test internal HTTP access (requires Apache to be running)
echo ""
echo "🌐 Internal HTTP test will run after Apache starts..."
echo ""

# Start Apache in foreground
echo "================================================"
echo "✅ Starting Apache on 0.0.0.0:${PORT}..."
echo "📡 Apache is binding to all network interfaces"
echo "🌐 Health check endpoint: http://0.0.0.0:${PORT}/health.php"
echo "================================================"
echo ""

# Use exec to replace the shell with apache2
exec apache2-foreground