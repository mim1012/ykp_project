# 🔧 Railway 환경 변수 수정 사항

## ⚠️ 수정이 필요한 환경 변수

### 1. APP_URL 수정
```bash
# 현재 (잘못됨)
APP_URL="${{RAILWAY_PUBLIC_DOMAIN}}"

# 수정 후 (올바름)
APP_URL="https://${{RAILWAY_PUBLIC_DOMAIN}}"
```

### 2. DB_HOST 옵션 (둘 중 하나 선택)

#### 옵션 A: Pooler 사용 (연결 풀링 - 추천)
```bash
DB_HOST="aws-1-ap-southeast-1.pooler.supabase.com"
DB_USERNAME="postgres.qwafwqxdcfpqqwpmphkm"
DB_PORT="5432"
```

#### 옵션 B: 직접 연결
```bash
DB_HOST="db.qwafwqxdcfpqqwpmphkm.supabase.co"
DB_USERNAME="postgres"
DB_PORT="5432"
```

### 3. 프로덕션용 변경 사항
```bash
# Staging → Production
APP_ENV="production"
APP_DEBUG="false"
LOG_LEVEL="error"
```

## ✅ 최종 권장 설정 (전체 복사용)

```env
# Application
APP_ENV="production"
APP_DEBUG="false"
APP_KEY="base64:NIbD5K47p+hHfpj168VZeE4+3v4wBmT9eTTHkr6ripk="
APP_NAME="YKP ERP"
APP_URL="https://${{RAILWAY_PUBLIC_DOMAIN}}"
APP_LOCALE="ko"
APP_FALLBACK_LOCALE="en"
APP_FAKER_LOCALE="ko_KR"

# Database (Supabase with Pooler)
DB_CONNECTION="pgsql"
DB_HOST="aws-1-ap-southeast-1.pooler.supabase.com"
DB_PORT="5432"
DB_DATABASE="postgres"
DB_USERNAME="postgres.qwafwqxdcfpqqwpmphkm"
DB_PASSWORD="rlawlgns2233@"
DB_SSLMODE="require"

# Supabase API
SUPABASE_URL="https://qwafwqxdcfpqqwpmphkm.supabase.co"
SUPABASE_ANON_KEY="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InF3YWZ3cXhkY2ZwcXF3cG1waGttIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTc3NzY5ODcsImV4cCI6MjA3MzM1Mjk4N30.wfFOhuAZ6kpAr_55yoj9VQp5-rhqZrSMaWxvJfkTx3k"

# Session & Cache
SESSION_DRIVER="database"
SESSION_LIFETIME="120"
SESSION_ENCRYPT="false"
SESSION_SECURE="true"
SESSION_HTTP_ONLY="true"
SESSION_SAME_SITE="lax"
CACHE_STORE="database"
QUEUE_CONNECTION="database"

# Logging
LOG_CHANNEL="stderr"
LOG_LEVEL="error"

# Features
FEATURE_EXCEL_INPUT="true"
FEATURE_ADVANCED_REPORTS="true"
FEATURE_SUPABASE_ENHANCED="true"

# Mail
MAIL_MAILER="log"
MAIL_FROM_ADDRESS="admin@ykp.com"
MAIL_FROM_NAME="YKP ERP"

# Other
BROADCAST_CONNECTION="log"
FILESYSTEM_DISK="local"
VITE_APP_NAME="YKP ERP"
```

## 📝 Railway 설정 방법

1. Railway 대시보드에서 **Variables** 탭 클릭
2. **RAW Editor** 모드 전환
3. 기존 변수 모두 삭제
4. 위의 "최종 권장 설정" 복사/붙여넣기
5. **Save** 클릭

## 🔍 확인 사항

- ✅ `APP_URL`에 `https://` 접두사 포함
- ✅ Pooler 연결 사용 (더 안정적)
- ✅ SSL 모드 활성화
- ✅ 프로덕션 로그 레벨 설정

## 🚀 배포 후 테스트

```bash
# Health check
https://your-app.up.railway.app/health

# 로그인 페이지
https://your-app.up.railway.app/login
```