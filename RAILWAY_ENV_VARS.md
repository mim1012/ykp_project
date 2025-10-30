# Railway 환경 변수 설정 가이드

## 🔐 Supabase PostgreSQL 연결 정보

Railway 대시보드에서 다음 환경 변수를 설정하세요:

### 데이터베이스 연결
```
DB_CONNECTION=pgsql
DB_HOST=db.qwafwqxdcfpqqwpmphkm.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=[REDACTED]
```

### Supabase API (선택사항)
```
SUPABASE_URL=https://qwafwqxdcfpqqwpmphkm.supabase.co
SUPABASE_ANON_KEY=[REDACTED]
```

### 애플리케이션 설정
```
APP_NAME="YKP ERP"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-app.up.railway.app
```

### 세션 및 캐시
```
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

## 📝 설정 방법

1. Railway 대시보드 접속
2. 프로젝트 선택
3. Variables 탭 클릭
4. "RAW Editor" 모드로 전환
5. 위 환경 변수 복사/붙여넣기
6. Save 클릭

## ⚠️ 중요 사항

- `APP_KEY`는 자동 생성됩니다
- `DB_HOST`는 `db.` 접두사를 사용합니다 (Supabase PostgreSQL 직접 연결)
- 비밀번호에 특수문자(@)가 포함되어 있으므로 따옴표 없이 그대로 입력

## 🔍 연결 테스트

배포 후 `/health` 엔드포인트를 확인하여 데이터베이스 연결 상태를 확인할 수 있습니다.