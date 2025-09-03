#!/usr/bin/env bash
set -e

PORT="${PORT:-8080}"

# Apache가 $PORT로 리슨
sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

php artisan key:generate --force || true
php artisan config:cache || true
php artisan route:cache  || true
php artisan view:cache   || true
php artisan migrate --force || true

exec apache2-foreground
