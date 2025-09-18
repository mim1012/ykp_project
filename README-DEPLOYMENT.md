# YKP Dashboard Railway 배포 가이드

## 🚀 긴급 수정사항 반영 완료

### ✅ 해결된 Critical Issues
1. **railway.toml startCommand 제거** - 존재하지 않는 start.sh 참조 제거
2. **프로젝트 경로 정합성** - Railway root 기준 빌드 컨텍스트 통일  
3. **Dockerfile 문법 오류** - sed 명령 한 줄 통합, AllowOverride All 설정
4. **vendor COPY 전략** - .dockerignore 최적화
5. **헬스체크 안정화** - `/healthz.php` 정적 엔드포인트

## 📋 배포 프로세스

### 1. 로컬 Vendor 준비 (필수 🔥)
```bash
cd "D:\Project\ykp-dashboard"

# Production 의존성 설치
composer install --no-dev --prefer-dist --optimize-autoloader

# vendor 폴더 생성 확인
dir vendor

# 빌드 테스트
npm ci
npm run build
```

### 2. Railway 환경변수 설정
```env
# Laravel Core
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:YOUR_32_CHARACTER_KEY_HERE
APP_URL=https://your-app.up.railway.app

# Database (Supabase PostgreSQL)
DB_CONNECTION=pgsql
DB_HOST=your-supabase-host.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=your-username
DB_PASSWORD=your-secure-password
DB_SSLMODE=require

# 초기 안정화 설정
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
CACHE_DRIVER=file

# Logging
LOG_CHANNEL=stderr
LOG_LEVEL=debug
```

### 3. 배포 전 체크리스트
- [ ] `vendor/` 폴더 존재 및 커밋됨
- [ ] `public/build/` 폴더 빌드됨  
- [ ] Railway Variables 모든 필수값 설정
- [ ] `DB_SSLMODE=require` 확인
- [ ] `/healthz.php` 엔드포인트 생성됨

### 4. 배포 실행
```bash
# Git 상태 확인
git status

# 변경사항 커밋
git add .
git commit -m "fix: Railway deployment critical issues

- Remove non-existent startCommand from railway.toml
- Fix Dockerfile paths for Railway root context
- Add static healthz.php endpoint
- Optimize vendor COPY strategy

🤖 Generated with Claude Code

Co-Authored-By: Claude <noreply@anthropic.com>"

# 배포
git push origin deploy-test
```

## 🔧 운영 환경별 설정

### Staging (deploy-test)
- `APP_ENV=staging`
- `APP_DEBUG=true` 
- `LOG_LEVEL=debug`
- `SESSION_DRIVER=file`
- `QUEUE_CONNECTION=sync`

### Production (main)
- `APP_ENV=production`
- `APP_DEBUG=false`
- `LOG_LEVEL=warning`
- `SESSION_DRIVER=redis` (Redis 사용 시)
- `QUEUE_CONNECTION=redis` (큐 사용 시)

## 🔍 트러블슈팅

### 즉시 컨테이너 종료 시
1. Railway Logs에서 "start.sh not found" 오류 확인 → railway.toml 수정됨
2. Apache 프로세스 시작 실패 → Dockerfile CMD 확인

### Laravel 라우팅 실패 시  
1. AllowOverride 설정 → Dockerfile에서 수정됨
2. .htaccess 파일 존재 확인 → `public/.htaccess`

### 빌드 실패 시
1. vendor 동기화: `composer install --no-dev` 재실행
2. 메모리 부족: NODE_OPTIONS 설정 확인됨
3. 경로 오류: Railway root 컨텍스트 수정됨

## 📊 헬스체크 & 모니터링

### 헬스체크 엔드포인트
- **URL**: `https://your-app.up.railway.app/healthz.php`
- **응답**: `{"status":"ok","timestamp":"2025-09-03 15:30:45 UTC","service":"ykp-dashboard","environment":"railway"}`
- **상태**: 200 OK (Laravel 부팅과 무관)

### 로컬 테스트
```bash
# 헬스체크 테스트
curl http://localhost:8000/healthz.php

# Docker 빌드 테스트  
docker build -t ykp-test .
docker run -p 8080:80 ykp-test

# 컨테이너 헬스체크
curl http://localhost:8080/healthz.php
```

## 🚨 롤백 방법

### 빠른 롤백
```bash
git revert HEAD
git push origin deploy-test
```

### 특정 커밋으로 롤백
```bash
git reset --hard f12f4d5  # 이전 안정 커밋
git push --force-with-lease origin deploy-test
```

### 환경변수만 롤백
Railway Dashboard > Variables에서 직접 수정

## ⚡ 성능 최적화 체크

- [ ] 메모리 사용량: Railway Metrics 확인
- [ ] 빌드 시간: 5분 이내 목표
- [ ] 컨테이너 시작 시간: 30초 이내
- [ ] 헬스체크 응답: 100ms 이내