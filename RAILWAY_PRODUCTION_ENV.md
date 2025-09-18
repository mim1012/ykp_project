# 🚀 Railway Production 환경변수 (최종)

## ✅ 수정된 프로덕션 설정

```env
# Application Settings
APP_ENV="production"
APP_DEBUG="false"
APP_KEY="base64:NIbD5K47p+hHfpj168VZeE4+3v4wBmT9eTTHkr6ripk="
APP_NAME="YKP ERP"
APP_URL="https://${{RAILWAY_PUBLIC_DOMAIN}}"
APP_LOCALE="ko"
APP_FALLBACK_LOCALE="en"
APP_FAKER_LOCALE="ko_KR"

# Logging (변경: error로 수정)
LOG_CHANNEL="stderr"
LOG_LEVEL="error"

# Database (Supabase Pooler)
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

# Features
FEATURE_EXCEL_INPUT="true"
FEATURE_ADVANCED_REPORTS="true"
FEATURE_SUPABASE_ENHANCED="true"

# Mail
MAIL_MAILER="log"
MAIL_FROM_ADDRESS="admin@ykp.com"
MAIL_FROM_NAME="YKP ERP"

# Others
BROADCAST_CONNECTION="log"
FILESYSTEM_DISK="local"
```

## 🔴 주요 변경사항

### 1. **프로덕션 설정 수정**
- `APP_NAME`: "YKP ERP Production" → "YKP ERP"
- `LOG_LEVEL`: "debug" → "error" (프로덕션은 error만)
- `MAIL_FROM_ADDRESS`: "staging@ykp.com" → "admin@ykp.com"

### 2. **제거할 변수들** (불필요)
```env
# 이 변수들은 제거해도 됨
PHP_CLI_SERVER_WORKERS="4"
APP_MAINTENANCE_DRIVER="file"
BCRYPT_ROUNDS="12"
PDO_ATTR_EMULATE_PREPARES="true"
PDO_ATTR_TIMEOUT="30"
DB_CONNECTION_RETRY_ATTEMPTS="5"
PGSQL_ATTR_SSL_MODE="require"
FEATURE_POSTGRESQL_BOOLEAN_FIX="true"
FEATURE_ENHANCED_ERROR_HANDLING="true"
FEATURE_IMPROVED_RETRY_LOGIC="true"
FEATURE_UI_V2="false"
FEATURE_EXCEL_INPUT_ROLLOUT="100"
AWS_ACCESS_KEY_ID=""
AWS_SECRET_ACCESS_KEY=""
AWS_DEFAULT_REGION="us-east-1"
AWS_BUCKET=""
AWS_USE_PATH_STYLE_ENDPOINT="false"
VITE_APP_NAME="YKP ERP Staging"
VITE_BACKEND_URL="https://endearing-dedication-production.up.railway.app"
```

### 3. **SESSION_DOMAIN 수정**
```env
# 현재
SESSION_DOMAIN=".up.railway.app"

# 수정 (빈 값으로 - 자동 감지)
SESSION_DOMAIN=""
```

## 💡 Railway Egress 비용 관련

Railway가 `APP_URL`이 public endpoint를 참조한다고 경고하지만:
- `APP_URL`은 브라우저에서 사용되는 URL이므로 PUBLIC_DOMAIN이 맞습니다
- 데이터베이스는 외부 Supabase를 사용하므로 관계없습니다
- 내부 서비스 간 통신이 없으므로 PRIVATE_DOMAIN 불필요

## 📋 적용 방법

1. Railway Dashboard → Variables 탭
2. 기존 변수 모두 삭제
3. 위의 "수정된 프로덕션 설정" 복사
4. RAW Editor에 붙여넣기
5. Save 클릭

## 🔍 배포 후 확인

```bash
# Health check
https://your-app.up.railway.app/health.php

# 메인 페이지
https://your-app.up.railway.app/

# 로그인 페이지
https://your-app.up.railway.app/login
```

## ⚠️ 중요 사항

1. **APP_KEY는 변경하지 마세요** - 기존 데이터 암호화에 사용됨
2. **DB_PASSWORD의 @는 그대로 입력** - 특수문자 이스케이프 불필요
3. **Pooler 연결 유지** - 더 안정적인 연결