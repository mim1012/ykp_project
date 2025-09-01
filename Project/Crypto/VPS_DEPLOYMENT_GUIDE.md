# 🚀 VPS 서버 배포 가이드

VPS 서버에서 암호화폐 자동매매 시스템 웹 대시보드를 배포하는 완전한 가이드.

## 📋 서버 요구사항

### 최소 사양
- **OS**: Ubuntu 18.04+ / CentOS 8+ / Debian 10+
- **RAM**: 4GB (권장 8GB)
- **Storage**: 20GB (권장 50GB)
- **CPU**: 2 vCPU (권장 4 vCPU)
- **Network**: 100Mbps+ 안정적인 연결

### 권장 VPS 제공업체
- **AWS EC2**: t3.medium 이상
- **Google Cloud**: e2-medium 이상  
- **DigitalOcean**: 4GB Droplet
- **Vultr**: High Frequency 4GB
- **Linode**: 4GB Nanode

---

## 🔧 1단계: 서버 초기 설정

### 서버 접속 및 업데이트
```bash
# SSH 접속
ssh root@your-server-ip

# 시스템 업데이트
apt update && apt upgrade -y

# 필수 패키지 설치
apt install -y curl git vim ufw fail2ban htop
```

### 보안 설정
```bash
# 방화벽 설정
ufw allow ssh
ufw allow 80
ufw allow 443
ufw allow 5000
ufw --force enable

# SSH 보안 강화
sed -i 's/#PermitRootLogin yes/PermitRootLogin no/' /etc/ssh/sshd_config
sed -i 's/#PasswordAuthentication yes/PasswordAuthentication no/' /etc/ssh/sshd_config
systemctl restart sshd

# Fail2ban 설정
systemctl enable fail2ban
systemctl start fail2ban
```

### 사용자 계정 생성
```bash
# 전용 사용자 생성
useradd -m -s /bin/bash crypto
usermod -aG sudo crypto

# SSH 키 설정 (선택사항)
mkdir -p /home/crypto/.ssh
cp ~/.ssh/authorized_keys /home/crypto/.ssh/
chown -R crypto:crypto /home/crypto/.ssh
chmod 700 /home/crypto/.ssh
chmod 600 /home/crypto/.ssh/authorized_keys
```

---

## 🐳 2단계: Docker 설치

### Docker 및 Docker Compose 설치
```bash
# Docker 설치
curl -fsSL https://get.docker.com -o get-docker.sh
sh get-docker.sh

# 사용자를 docker 그룹에 추가
usermod -aG docker crypto

# Docker Compose 설치
curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
chmod +x /usr/local/bin/docker-compose

# 서비스 시작 및 활성화
systemctl start docker
systemctl enable docker

# 설치 확인
docker --version
docker-compose --version
```

---

## 📥 3단계: 프로젝트 배포

### 저장소 클론
```bash
# crypto 사용자로 전환
su - crypto

# 프로젝트 디렉토리 생성
mkdir -p /opt/crypto-trading
cd /opt/crypto-trading

# Git 클론
git clone https://github.com/mim1012/Crypto.git .

# 권한 설정
chmod +x deploy.sh
```

### 환경 설정
```bash
# 환경 변수 파일 생성
cp .env.example .env
nano .env
```

### .env 파일 설정 예시
```env
# Production Environment
FLASK_ENV=production
TRADING_ENV=production
SECRET_KEY=your-very-secure-secret-key-minimum-32-characters

# Database
DATABASE_URL=postgresql://crypto:crypto123@db:5432/crypto_trading

# Redis
REDIS_URL=redis://redis:6379/0

# Security
JWT_SECRET_KEY=your-jwt-secret-key-different-from-secret-key
PASSWORD_SALT=your-unique-salt-for-passwords

# Trading APIs (실제 키로 변경 필요)
BINANCE_API_KEY=your-binance-api-key
BINANCE_SECRET_KEY=your-binance-secret-key
BYBIT_API_KEY=your-bybit-api-key
BYBIT_SECRET_KEY=your-bybit-secret-key

# Optional: Email notifications
SMTP_SERVER=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-email-password
```

---

## 🚀 4단계: 자동 배포 실행

### 원클릭 배포
```bash
# 자동 배포 스크립트 실행
./deploy.sh

# 또는 수동 단계별 실행
docker-compose build
docker-compose up -d
```

### 서비스 상태 확인
```bash
# 컨테이너 상태 확인
docker-compose ps

# 로그 확인
docker-compose logs -f crypto-web

# 헬스 체크
curl http://localhost:5000/health
```

---

## 🌐 5단계: 도메인 및 SSL 설정

### 도메인 연결
```bash
# DNS 설정 (도메인 제공업체에서)
A record: yourdomain.com -> your-server-ip
A record: www.yourdomain.com -> your-server-ip
```

### Let's Encrypt SSL 인증서
```bash
# Certbot 설치
sudo apt install certbot

# SSL 인증서 발급
sudo certbot certonly --standalone -d yourdomain.com -d www.yourdomain.com

# 인증서 위치 확인
ls /etc/letsencrypt/live/yourdomain.com/

# Nginx 설정 업데이트
sudo nano nginx/nginx.conf
# ssl_certificate와 ssl_certificate_key 경로 수정

# 서비스 재시작
docker-compose restart nginx
```

### Nginx SSL 설정 예시
```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;

    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    
    # SSL 보안 설정
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    ssl_prefer_server_ciphers off;
    
    # 나머지 설정...
}
```

---

## 📊 6단계: 모니터링 설정

### Grafana 대시보드 접속
```bash
# Grafana 접속
URL: http://your-server-ip:3000
기본 계정: admin / admin123

# 첫 로그인 후 비밀번호 변경 필요
```

### 시스템 모니터링 설정
```bash
# 시스템 리소스 모니터링
htop  # CPU, 메모리 실시간 확인

# Docker 리소스 확인
docker stats

# 로그 모니터링
tail -f logs/trading_system.log
```

### 자동 백업 설정
```bash
# 일일 백업 스크립트 생성
cat > /home/crypto/backup.sh << 'EOF'
#!/bin/bash
DATE=$(date +%Y%m%d)
BACKUP_DIR="/home/crypto/backups"
mkdir -p $BACKUP_DIR

# 설정 백업
tar -czf $BACKUP_DIR/config_$DATE.tar.gz config/

# 데이터베이스 백업
docker exec crypto-trading-db pg_dump -U crypto crypto_trading > $BACKUP_DIR/db_$DATE.sql

# 오래된 백업 삭제 (30일)
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete

echo "Backup completed: $DATE"
EOF

chmod +x /home/crypto/backup.sh

# 크론탭 설정 (매일 새벽 3시)
(crontab -l 2>/dev/null; echo "0 3 * * * /home/crypto/backup.sh") | crontab -
```

---

## 🔄 7단계: 운영 및 유지보수

### 일상 운영 명령어
```bash
# 서비스 시작/중지
docker-compose up -d        # 시작
docker-compose stop         # 중지
docker-compose restart      # 재시작

# 로그 확인
docker-compose logs -f crypto-web     # 실시간 로그
docker-compose logs --tail=100        # 최근 100줄

# 상태 확인
docker-compose ps          # 컨테이너 상태
docker stats               # 리소스 사용량
```

### 업데이트 방법
```bash
# 1. 백업 실행
./backup.sh

# 2. 코드 업데이트
git pull origin main

# 3. 서비스 재빌드 및 재시작
docker-compose down
docker-compose build --no-cache
docker-compose up -d

# 4. 상태 확인
docker-compose ps
curl http://localhost:5000/health
```

### 문제 진단
```bash
# 컨테이너 로그 확인
docker-compose logs crypto-web

# 컨테이너 내부 접속
docker exec -it crypto-trading-web bash

# 데이터베이스 접속
docker exec -it crypto-trading-db psql -U crypto -d crypto_trading

# Redis 접속
docker exec -it crypto-trading-redis redis-cli
```

---

## 🔧 고급 설정

### 성능 최적화
```bash
# 1. 시스템 튜닝
echo 'vm.swappiness=10' >> /etc/sysctl.conf
echo 'net.core.rmem_max=134217728' >> /etc/sysctl.conf
sysctl -p

# 2. Docker 최적화
cat > /etc/docker/daemon.json << 'EOF'
{
    "log-driver": "json-file",
    "log-opts": {
        "max-size": "10m",
        "max-file": "3"
    },
    "storage-driver": "overlay2"
}
EOF

systemctl restart docker
```

### 보안 강화
```bash
# 1. IP 접근 제한 (선택적)
ufw allow from YOUR_IP_ADDRESS to any port 5000

# 2. 2FA 설정 (권장)
# 웹 대시보드 로그인 후 보안 설정에서 설정

# 3. API 키 권한 최소화
# 거래소에서 거래 권한만 허용, 출금 권한 비활성화
```

### 알림 설정
```bash
# 텔레그램 봇 설정 (선택적)
# 1. @BotFather에서 봇 생성
# 2. .env에 TELEGRAM_BOT_TOKEN 추가
# 3. 웹 대시보드에서 채팅 ID 설정
```

---

## 📊 모니터링 대시보드

### Grafana 대시보드 설정
1. **Grafana 접속**: `http://your-server-ip:3000`
2. **데이터소스 추가**: PostgreSQL, Redis
3. **대시보드 임포트**: `grafana/dashboard.json`

### 주요 모니터링 지표
- **시스템 성능**: CPU, 메모리, 디스크, 네트워크
- **거래 성과**: 일일 수익률, 승률, 드로우다운
- **API 상태**: 응답시간, 오류율, 연결상태
- **보안 이벤트**: 로그인 시도, API 오류

---

## 🚨 비상 상황 대응

### 긴급 중지
```bash
# 모든 서비스 즉시 중지
docker-compose down

# 특정 서비스만 중지
docker-compose stop crypto-web
```

### 데이터 복구
```bash
# 백업에서 설정 복구
cd /home/crypto/backups
tar -xzf config_YYYYMMDD.tar.gz -C /opt/crypto-trading/

# 데이터베이스 복구
docker exec -i crypto-trading-db psql -U crypto -d crypto_trading < db_YYYYMMDD.sql
```

### 로그 분석
```bash
# 오류 로그 확인
grep -i error logs/trading_system.log | tail -20

# 거래 로그 확인
grep -i "position\|trade" logs/trading_system.log | tail -20

# 시스템 로그 확인
journalctl -u docker -f
```

---

## 📞 지원 및 문의

### 24/7 모니터링 설정
```bash
# 시스템 상태 체크 스크립트
cat > /home/crypto/health_check.sh << 'EOF'
#!/bin/bash
if ! curl -f http://localhost:5000/health >/dev/null 2>&1; then
    echo "$(date): Application health check failed" >> /var/log/crypto-health.log
    docker-compose restart crypto-web
fi
EOF

# 5분마다 헬스 체크
(crontab -l 2>/dev/null; echo "*/5 * * * * /home/crypto/health_check.sh") | crontab -
```

### 연락처
- **긴급 상황**: emergency@crypto-trading.com
- **기술 지원**: support@crypto-trading.com
- **업데이트 알림**: updates@crypto-trading.com

---

## ✅ 배포 체크리스트

### 배포 전 확인사항
- [ ] 서버 사양 충족 확인
- [ ] 도메인 DNS 설정 완료
- [ ] API 키 준비 완료
- [ ] 보안 설정 완료
- [ ] 백업 계획 수립

### 배포 후 확인사항
- [ ] 웹 대시보드 정상 접속
- [ ] API 연결 상태 확인
- [ ] SSL 인증서 정상 작동
- [ ] 모니터링 시스템 작동
- [ ] 백업 스크립트 테스트
- [ ] 알림 시스템 테스트

### 운영 중 정기 점검
- [ ] 주간 성과 리뷰
- [ ] 월간 보안 점검
- [ ] 분기별 업데이트 적용
- [ ] 반기별 백업 검증

---

**성공적인 VPS 배포를 통해 24시간 무인 자동매매 시스템을 운영하실 수 있습니다!** 🎉