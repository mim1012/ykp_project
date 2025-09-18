#!/bin/bash
echo "🚀 Starting YKP Dashboard on port ${PORT:-8080}..."

# Update Apache to listen on Railway's PORT
if [ ! -z "$PORT" ]; then
    echo "🔧 Configuring Apache for port $PORT..."
    sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
    sed -i "s/:80/:$PORT/g" /etc/apache2/sites-available/000-default.conf
fi

# Only critical tasks
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force 2>/dev/null || true
fi

# Try migrations once
php artisan migrate --force 2>/dev/null || true

echo "✅ Starting Apache on port ${PORT:-8080}..."
exec apache2-foreground