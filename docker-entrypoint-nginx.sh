#!/bin/sh
set -e

echo "🚀 Starting YKP Dashboard with Nginx + PHP-FPM..."

# Sync environment variables to .env file
if [ -f "sync-env-vars.php" ]; then
    echo "🔄 Syncing environment variables to .env..."
    php sync-env-vars.php || true
fi

# Fix Filament autoload if needed
if [ -f "fix-filament-autoload.php" ]; then
    echo "🔧 Fixing Filament autoload..."
    php fix-filament-autoload.php || true
fi

# Fix Carbon Test trait issue
if [ -f "fix-carbon-production.php" ]; then
    echo "🔧 Fixing Carbon Test trait..."
    php fix-carbon-production.php || true
fi

# Generate app key if needed
if ! grep -q "^APP_KEY=.\+" .env 2>/dev/null; then
    echo "🔑 Generating application key..."
    php artisan key:generate --force || true
fi

# Run migrations
echo "📦 Running migrations..."
php artisan migrate --force 2>/dev/null || echo "Migrations skipped or already run"

# Test PHP-FPM connection
echo "🔍 Testing PHP-FPM..."
php-fpm -t || echo "⚠️ PHP-FPM test failed"

# Test Nginx configuration
echo "🔍 Testing Nginx..."
nginx -t || echo "⚠️ Nginx test failed"

# Create test endpoint
cat > /var/www/html/public/test-fpm.php << 'EOF'
<?php
echo "PHP-FPM is working!\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Server: " . php_sapi_name() . "\n";
EOF

echo "✅ Starting supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf