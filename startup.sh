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

# 1) Configure Apache ports and vhost
echo ""
echo "📡 Configuring Apache to listen on 0.0.0.0:${PORT}..."
echo "Listen 0.0.0.0:${PORT}" > /etc/apache2/ports.conf

# Replace PORT placeholder in VirtualHost config
sed -i "s/\${PORT}/${PORT}/g" /etc/apache2/sites-available/001-app.conf

# Ensure correct sites are enabled
a2dissite 000-default >/dev/null 2>&1 || true
a2ensite 001-app >/dev/null 2>&1 || true

# Ensure ServerName is set
grep -q "ServerName" /etc/apache2/apache2.conf || echo "ServerName localhost" >> /etc/apache2/apache2.conf

# 2) CRITICAL DIAGNOSTICS
echo ""
echo "🔍 === APACHE CONFIGURATION DIAGNOSTICS ==="
echo ""
echo "---- apache2ctl -S (VirtualHost Configuration) ----"
apache2ctl -S || true
echo ""
echo "---- ports.conf ----"
cat /etc/apache2/ports.conf
echo ""
echo "---- 001-app.conf (first 50 lines) ----"
head -50 /etc/apache2/sites-available/001-app.conf
echo ""
echo "---- Active sites in sites-enabled ----"
ls -la /etc/apache2/sites-enabled/
echo ""
echo "---- DocumentRoot search ----"
grep -n "DocumentRoot" /etc/apache2/sites-available/001-app.conf || true
echo ""
echo "---- File existence check ----"
echo "📍 Working directory: $(pwd)"
echo "📍 health.txt exists: $(test -f /var/www/html/public/health.txt && echo 'YES ✅' || echo 'NO ❌')"
echo "📍 health.php exists: $(test -f /var/www/html/public/health.php && echo 'YES ✅' || echo 'NO ❌')"
echo "📍 .htaccess exists: $(test -f /var/www/html/public/.htaccess && echo 'YES ✅' || echo 'NO ❌')"
echo "📍 index.php exists: $(test -f /var/www/html/public/index.php && echo 'YES ✅' || echo 'NO ❌')"
echo ""

# Test Apache configuration
echo "🔍 Testing Apache configuration..."
apache2ctl configtest

# 3) Start Apache temporarily for internal tests
echo ""
echo "🧪 Starting Apache for internal tests..."
apache2-foreground &
APACHE_PID=$!

# Wait for Apache to start
sleep 3

# Internal HTTP tests
echo ""
echo "🌐 === INTERNAL HTTP TESTS ==="
echo "Testing with curl from inside container..."
echo ""
echo -n "health.txt: "
curl -sS -o /dev/null -w "%{http_code}\n" http://127.0.0.1:${PORT}/health.txt || echo "FAILED"
echo -n "health.php: "
curl -sS -o /dev/null -w "%{http_code}\n" http://127.0.0.1:${PORT}/health.php || echo "FAILED"
echo -n "index.php: "
curl -sS -o /dev/null -w "%{http_code}\n" http://127.0.0.1:${PORT}/ || echo "FAILED"
echo ""

# Check Apache error log
echo "---- Last 20 lines of Apache error log ----"
tail -20 /var/log/apache2/error.log 2>/dev/null || echo "No error log found"
echo ""

# Kill test Apache
kill $APACHE_PID 2>/dev/null || true
wait $APACHE_PID 2>/dev/null || true

# 4) Final startup
echo "================================================"
echo "✅ Starting Apache on 0.0.0.0:${PORT} (production mode)..."
echo "📡 Apache is binding to all network interfaces"
echo "🌐 Health check endpoints:"
echo "   - Static: http://0.0.0.0:${PORT}/health.txt"
echo "   - PHP: http://0.0.0.0:${PORT}/health.php"
echo "================================================"
echo ""

# Use exec to replace the shell with apache2
exec apache2-foreground