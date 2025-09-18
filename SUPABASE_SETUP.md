# 🗃️ YKP Dashboard Supabase 연동 가이드

## 📋 Supabase 프로젝트 생성

### 1단계: Supabase 계정 생성
1. https://supabase.com 접속
2. GitHub 로그인
3. "New Project" 클릭
4. **Organization**: 개인 계정 선택
5. **Project name**: `ykp-dashboard`
6. **Database Password**: 강력한 비밀번호 설정 ⚠️ 꼭 기록!
7. **Region**: Northeast Asia (Seoul) 선택

### 2단계: 연결 정보 수집
Supabase 대시보드에서:
1. **Settings** → **Database** 
2. **Connection string** 복사
3. **Project URL** 복사  
4. **API Keys** → `anon` key, `service_role` key 복사

### 3단계: 환경 설정
```bash
# .env 파일 업데이트
cp .env.supabase .env

# 아래 정보 입력:
DB_HOST=db.xxxxxxxxxxxxx.supabase.co
DB_PASSWORD=your-database-password
SUPABASE_URL=https://xxxxxxxxxxxxx.supabase.co
SUPABASE_ANON_KEY=your-anon-key
SUPABASE_SERVICE_ROLE_KEY=your-service-role-key
```

## 🚀 마이그레이션 실행

### SQLite → Supabase 데이터 이전
```bash
# 1. 기존 데이터 백업
php artisan db:backup-current

# 2. Supabase 연결 테스트
php artisan migrate:status

# 3. 마이그레이션 실행
php artisan migrate

# 4. 시더 실행 (기본 데이터)
php artisan db:seed

# 5. 기존 SQLite 데이터 이전 (선택사항)
php artisan db:migrate-from-sqlite
```

## 🔄 실시간 동기화 설정

### Supabase RLS (Row Level Security) 설정
```sql
-- 사용자별 데이터 접근 제어
CREATE POLICY "매장 직원은 자기 매장만" ON sales
    FOR ALL USING (
        auth.uid()::text = (
            SELECT id::text FROM users 
            WHERE email = auth.email() AND store_id = sales.store_id
        )
    );

CREATE POLICY "지사 관리자는 지사 매장만" ON sales  
    FOR ALL USING (
        auth.uid()::text = (
            SELECT id::text FROM users
            WHERE email = auth.email() AND branch_id = sales.branch_id
        )
    );

CREATE POLICY "본사는 모든 데이터" ON sales
    FOR ALL USING (
        auth.uid()::text = (
            SELECT id::text FROM users
            WHERE email = auth.email() AND role = 'headquarters'
        )
    );
```

## 📊 매장관리 기능 (Supabase 연동)

### 실시간 기능들
1. **매장 추가** → 즉시 모든 사용자에게 반영
2. **계정 생성** → Supabase Auth 연동
3. **데이터 입력** → 실시간 동기화
4. **권한 변경** → 즉시 접근 제어 적용

### API 엔드포인트
- `GET /api/stores` - 매장 목록 (권한별 필터링)
- `POST /api/stores` - 매장 추가 (본사만)
- `POST /api/stores/{id}/create-user` - 계정 생성
- `PUT /api/stores/{id}` - 매장 정보 수정
- `DELETE /api/stores/{id}` - 매장 삭제

## 🧪 테스트 시나리오

### 매장 추가 → 계정 생성 → 데이터 입력 → 조회
1. **본사**: 새 매장 "테스트매장" 추가
2. **본사**: 해당 매장용 계정 생성 (`test-store@ykp.com`)
3. **매장**: 새 계정으로 로그인 → 개통표 데이터 입력
4. **지사**: 해당 지사면 데이터 조회 가능
5. **본사**: 모든 매장 데이터 통합 조회

**Supabase 연동 시 모든 데이터가 실시간으로 동기화됩니다!** 🔄