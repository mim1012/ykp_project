#!/bin/bash

# VPS 서버 원클릭 배포 스크립트
# 암호화폐 자동매매 시스템 웹 대시보드 배포

set -e  # 오류 시 즉시 중단

echo "🚀 Crypto Trading System VPS Deployment"
echo "========================================="

# 환경 변수 확인
if [ -z "$SECRET_KEY" ]; then
    echo "⚠️  WARNING: SECRET_KEY not set. Using default (NOT SECURE for production)"
    export SECRET_KEY="your-very-secure-secret-key-change-this"
fi

# 시스템 업데이트
echo "📦 Updating system packages..."
sudo apt-get update
sudo apt-get install -y docker.io docker-compose git curl

# Docker 서비스 시작
echo "🐳 Starting Docker service..."
sudo systemctl start docker
sudo systemctl enable docker

# 사용자를 docker 그룹에 추가
sudo usermod -aG docker $USER

# 프로젝트 디렉토리 생성
echo "📁 Setting up project directory..."
PROJECT_DIR="/opt/crypto-trading"
sudo mkdir -p $PROJECT_DIR
sudo chown $USER:$USER $PROJECT_DIR

cd $PROJECT_DIR

# Git 저장소 클론 (이미 있다면 업데이트)
if [ -d ".git" ]; then
    echo "🔄 Updating existing repository..."
    git pull origin main
else
    echo "📥 Cloning repository..."
    git clone https://github.com/mim1012/Crypto.git .
fi

# 환경 설정 파일 생성
echo "⚙️  Creating environment configuration..."
cat > .env << EOF
# Production Environment Configuration
FLASK_ENV=production
TRADING_ENV=production
SECRET_KEY=$SECRET_KEY

# Database Configuration
DATABASE_URL=postgresql://crypto:crypto123@db:5432/crypto_trading

# Redis Configuration  
REDIS_URL=redis://redis:6379/0

# Security Settings
JWT_SECRET_KEY=$SECRET_KEY
PASSWORD_SALT=crypto-trading-salt-2025

# Trading Configuration
BINANCE_API_KEY=your-binance-api-key
BINANCE_SECRET_KEY=your-binance-secret-key
BYBIT_API_KEY=your-bybit-api-key
BYBIT_SECRET_KEY=your-bybit-secret-key

# Performance Settings
MAX_WORKERS=4
WORKER_CONNECTIONS=1000
REDIS_MAX_MEMORY=256mb
EOF

# SSL 인증서 디렉토리 생성
echo "🔐 Setting up SSL directory..."
mkdir -p nginx/ssl

# Nginx 설정 생성
echo "🌐 Creating Nginx configuration..."
mkdir -p nginx
cat > nginx/nginx.conf << 'EOF'
events {
    worker_connections 1024;
}

http {
    upstream crypto_app {
        server crypto-web:5000;
    }

    server {
        listen 80;
        server_name _;

        # Redirect HTTP to HTTPS
        return 301 https://$server_name$request_uri;
    }

    server {
        listen 443 ssl http2;
        server_name _;

        # SSL configuration (update paths as needed)
        ssl_certificate /etc/nginx/ssl/cert.pem;
        ssl_certificate_key /etc/nginx/ssl/key.pem;
        
        # Security headers
        add_header X-Frame-Options DENY;
        add_header X-Content-Type-Options nosniff;
        add_header X-XSS-Protection "1; mode=block";

        # Main application
        location / {
            proxy_pass http://crypto_app;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
        }

        # WebSocket support
        location /socket.io/ {
            proxy_pass http://crypto_app;
            proxy_http_version 1.1;
            proxy_set_header Upgrade $http_upgrade;
            proxy_set_header Connection "upgrade";
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
        }
    }
}
EOF

# Docker 이미지 빌드
echo "🏗️  Building Docker images..."
docker-compose build

# 서비스 시작
echo "▶️  Starting services..."
docker-compose up -d

# 서비스 상태 확인
echo "🔍 Checking service status..."
sleep 10
docker-compose ps

# 헬스 체크
echo "💚 Running health checks..."
for i in {1..30}; do
    if curl -f http://localhost:5000/health 2>/dev/null; then
        echo "✅ Application is healthy!"
        break
    else
        echo "⏳ Waiting for application to start... ($i/30)"
        sleep 2
    fi
done

# 로그 확인
echo "📄 Recent logs:"
docker-compose logs --tail=20 crypto-web

echo ""
echo "🎉 Deployment completed!"
echo "========================================="
echo "Web Dashboard: http://$(curl -s ifconfig.me)"
echo "Monitoring: http://$(curl -s ifconfig.me):3000 (admin/admin123)"
echo ""
echo "Next steps:"
echo "1. Update API keys in the web dashboard settings"
echo "2. Configure SSL certificates in nginx/ssl/"
echo "3. Set up domain name (optional)"
echo "4. Configure firewall rules"
echo ""
echo "Useful commands:"
echo "- View logs: docker-compose logs -f crypto-web"
echo "- Restart: docker-compose restart"
echo "- Stop: docker-compose down"
echo "- Update: git pull && docker-compose up -d --build"