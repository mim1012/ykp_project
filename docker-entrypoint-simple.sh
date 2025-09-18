#!/bin/bash
echo "🚀 Starting YKP Dashboard (Simple Mode)..."

# Only critical tasks
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force 2>/dev/null || true
fi

# Try migrations once
php artisan migrate --force 2>/dev/null || true

echo "✅ Starting Apache..."
exec apache2-foreground