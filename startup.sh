#!/bin/bash
set -e

echo "🚀 Starting YKP Dashboard"
echo "========================"

# Railway가 넣어준 PORT (8080)을 사용
export PORT=${PORT:-8080}
echo "📡 PORT: ${PORT}"

# Apache 포트 설정 덮어쓰기
echo "Listen 0.0.0.0:${PORT}" > /etc/apache2/ports.conf

# vhost 파일의 포트를 실제 PORT로 교체 (정확한 패턴 매칭)
# *:숫자+> 패턴을 PORT로 교체 (재시작 시 중복 방지)
sed -i -E "s/\*:[0-9]+>/*:${PORT}>/" /etc/apache2/sites-available/001-app.conf

# Sites 활성화
a2dissite 000-default >/dev/null 2>&1 || true
a2ensite 001-app >/dev/null 2>&1 || true

# Basic .env setup
if [ ! -f .env ]; then
    cp .env.example .env 2>/dev/null || echo "APP_KEY=" > .env
fi

# Fix permissions
chown -R www-data:www-data /var/www/html 2>/dev/null || true

# Quick verification
echo ""
echo "📍 Configuration Check:"
echo "Ports Config: $(cat /etc/apache2/ports.conf)"
echo "VirtualHost: $(grep -m1 'VirtualHost' /etc/apache2/sites-available/001-app.conf)"
echo "Files: health.txt=$(test -f /var/www/html/public/health.txt && echo '✅' || echo '❌')"
echo ""

# Internal test after 5 seconds
(sleep 5 && echo "📍 Internal test:" && curl -sS http://127.0.0.1:${PORT}/health.txt 2>&1 && echo " ✅") &

echo "========================"
echo "📡 Starting Apache on 0.0.0.0:${PORT}"
echo "========================"

# Start Apache
apache2-foreground
