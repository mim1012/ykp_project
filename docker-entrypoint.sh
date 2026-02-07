#!/bin/bash

echo "Starting YKP Dashboard..."
echo "Environment: ${APP_ENV:-production}"

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ]; then
    echo "Generating application key..."
    php artisan key:generate --force || echo "Key generation failed, continuing..."
fi

# Clear caches
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan cache:clear || true

# Run migrations
echo "Running migrations..."
php artisan migrate --force || echo "Migrations failed or already run"

# Optimize for production
if [ "$APP_ENV" = "production" ]; then
    echo "Optimizing for production..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

# Create storage link
php artisan storage:link || echo "Storage link already exists"

echo "Starting Apache on port ${PORT:-8080}..."
exec apache2-foreground
