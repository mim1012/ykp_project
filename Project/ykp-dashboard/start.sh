#!/usr/bin/env bash
set -euo pipefail

# Default working dir inside container image
cd "${APP_WORKDIR:-/var/www/html}" || cd /var/www/html

PORT="${PORT:-8080}"

# Configure Apache to listen on $PORT
if [ -f /etc/apache2/ports.conf ]; then
  sed -ri "s/^Listen [0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf || true
  if ! grep -q "Listen ${PORT}" /etc/apache2/ports.conf; then
    echo "Listen ${PORT}" >> /etc/apache2/ports.conf
  fi
fi

if [ -f /etc/apache2/sites-enabled/000-default.conf ]; then
  sed -ri "s#<VirtualHost \*:[0-9]+>#<VirtualHost *:${PORT}>#" /etc/apache2/sites-enabled/000-default.conf || true
fi

# Laravel optimizations & migrations
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Run migrations in production containers
php artisan migrate --force || true

exec apache2-foreground

