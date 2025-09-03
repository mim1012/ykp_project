#!/bin/bash
set -e

echo "🚀 Starting YKP ERP Laravel Application..."

# Set Apache port from Railway PORT environment variable
APACHE_PORT=${PORT:-80}

# Update Apache ports.conf
echo "Listen ${APACHE_PORT}" > /etc/apache2/ports.conf

# Apply dynamic port to Apache vhost configuration
envsubst '${APACHE_PORT}' < /etc/apache2/sites-available/000-default.conf.template > /etc/apache2/sites-available/000-default.conf

echo "📝 Apache configured for port ${APACHE_PORT}"

# Laravel application setup
echo "🔧 Setting up Laravel environment..."

# Copy environment file if not exists
if [ ! -f .env ]; then
    cp .env.example .env
    echo "📄 Environment file created from example"
fi

# Generate application key
echo "🔑 Generating application key..."
php artisan key:generate --force

# Create SQLite database if needed
if [ "$DB_CONNECTION" == "sqlite" ]; then
    echo "💾 Setting up SQLite database..."
    mkdir -p database
    touch database/database.sqlite
    chmod 664 database/database.sqlite
    chown www-data:www-data database/database.sqlite
fi

# Run database migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

# Laravel caching for production performance
echo "⚡ Caching Laravel configuration..."
php artisan config:cache
php artisan route:cache  
php artisan view:cache

# Clear any existing problematic caches
echo "🧹 Clearing temporary caches..."
php artisan cache:clear || true

# Set proper file permissions
echo "🔐 Setting file permissions..."
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html/storage
chmod -R 755 /var/www/html/bootstrap/cache

# Verify Laravel setup
echo "✅ Laravel application ready"
php artisan --version

# Start Apache in foreground mode
echo "🌐 Starting Apache web server on port ${APACHE_PORT}..."
exec apache2-foreground