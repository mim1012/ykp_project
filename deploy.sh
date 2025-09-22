#!/bin/bash

# YKP Dashboard - Railway Deployment Script
echo "🚀 Starting YKP Dashboard deployment..."

# Exit on any error
set -e

# Environment variables check
echo "📋 Checking environment variables..."
required_vars=("DB_HOST" "DB_DATABASE" "DB_USERNAME" "DB_PASSWORD" "APP_KEY")
for var in "${required_vars[@]}"; do
    if [ -z "${!var}" ]; then
        echo "❌ Error: $var is not set"
        exit 1
    fi
done
echo "✅ Environment variables OK"

# Clear caches
echo "🧹 Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Install dependencies
echo "📦 Installing dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Install frontend dependencies and build
echo "🏗️ Building frontend assets..."
npm ci --only=production
npm run build

# Database operations
echo "🗄️ Running database operations..."
php artisan migrate --force

# Seed production data if needed
if [ "$SEED_PRODUCTION" = "true" ]; then
    echo "🌱 Seeding production data..."
    php artisan db:seed --force
fi

# Cache configurations for production
echo "⚡ Caching configurations..."
php artisan route:cache
php artisan view:cache

# Set proper permissions
echo "🔐 Setting permissions..."
chmod -R 755 storage
chmod -R 755 bootstrap/cache

# Optimize autoloader
echo "🚀 Optimizing autoloader..."
composer dump-autoload --optimize

# Final health check
echo "🔍 Running health check..."
if php artisan --version > /dev/null 2>&1; then
    echo "✅ Laravel is running correctly"
else
    echo "❌ Laravel health check failed"
    exit 1
fi

echo "🎉 Deployment completed successfully!"
echo "📊 YKP Dashboard is ready to serve!"

# Log deployment
echo "$(date): YKP Dashboard deployed successfully" >> storage/logs/deployment.log