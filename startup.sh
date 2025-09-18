#!/bin/bash
set -e

echo "🚀 Starting YKP Dashboard - MINIMAL MODE"
echo "=========================================="

# Railway PORT (should be 80)
export PORT=${PORT:-80}
echo "📡 PORT: $PORT"

# Basic .env setup
if [ ! -f .env ]; then
    cp .env.example .env 2>/dev/null || echo "APP_KEY=" > .env
fi

# Fix permissions
chown -R www-data:www-data /var/www/html 2>/dev/null || true

# Override ports.conf with PORT
echo "Listen 0.0.0.0:${PORT}" > /etc/apache2/ports.conf

# Quick diagnostics
echo ""
echo "📍 Files Check:"
ls -la /var/www/html/public/health.* 2>/dev/null || echo "NO HEALTH FILES!"
echo ""

echo "📍 Apache Config Check:"
apache2ctl -S 2>&1 | head -20
echo ""

echo "📍 Ports Config:"
cat /etc/apache2/ports.conf
echo ""

echo "📍 Network Interfaces:"
netstat -tlpn 2>/dev/null | grep -E ":${PORT}|apache" || echo "Will bind after start"
echo ""

# Test with curl if Apache starts
(sleep 5 && echo "📍 Internal Test after 5 seconds:" && curl -sS http://127.0.0.1:${PORT}/health.txt 2>&1 && echo " ✅") &

echo "=========================================="
echo "✅ Starting Apache on 0.0.0.0:${PORT}"
echo "=========================================="

# Start Apache directly without exec to see errors
apache2-foreground