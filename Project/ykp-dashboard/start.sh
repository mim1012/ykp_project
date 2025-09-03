#!/bin/bash
set -e

echo "🚀 Starting YKP ERP Laravel Application..."

# Set Apache port from Railway PORT environment variable
APACHE_PORT=${PORT:-80}

# Update Apache configuration with correct port
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${APACHE_PORT}>/" /etc/apache2/sites-available/000-default.conf
sed -i "s/Listen 80/Listen ${APACHE_PORT}/" /etc/apache2/ports.conf

echo "📝 Apache configured for port ${APACHE_PORT}"

# Laravel setup and optimization
echo "🔧 Setting up Laravel environment..."

# Generate application key if not set
php artisan key:generate --force

# Create SQLite database
echo "💾 Setting up SQLite database..."
mkdir -p database
touch database/database.sqlite
chmod 664 database/database.sqlite

# Run database migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

# Cache Laravel configuration for performance
echo "⚡ Caching Laravel configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set proper permissions
echo "🔐 Setting file permissions..."
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html/storage
chmod -R 755 /var/www/html/bootstrap/cache

# Clear any existing caches that might interfere
php artisan cache:clear || true

# Start Apache in foreground
echo "🌐 Starting Apache web server on port ${APACHE_PORT}..."
exec apache2-ctl -D FOREGROUND