# 📦 YKP ERP Supabase 백업 가이드

## 🚀 빠른 시작

### 1️⃣ Supabase 로그인
```bash
supabase login
```

### 2️⃣ 프로젝트 ID 확인
```bash
supabase projects list
```

### 3️⃣ 백업 실행
```bash
# Windows
backup_supabase.bat YOUR_PROJECT_ID

# 수동 백업
supabase db dump --project-id YOUR_PROJECT_ID --file "backups/backup_$(date +%Y%m%d_%H%M%S).sql"
```

## 📋 백업 유형

### 🗄️ 전체 백업 (권장)
```bash
supabase db dump --project-id YOUR_PROJECT_ID --file "backups/full_backup.sql"
```
- **포함**: 모든 테이블 스키마 + 데이터
- **용도**: 완전한 시스템 복원
- **주기**: 매일 또는 주요 업데이트 전

### 🏗️ 스키마만 백업
```bash
supabase db dump --project-id YOUR_PROJECT_ID --schema-only --file "backups/schema_backup.sql"
```
- **포함**: 테이블 구조, 인덱스, 제약조건
- **용도**: 새 환경 구축
- **크기**: 매우 작음

### 📊 특정 테이블 백업
```bash
# 판매 데이터만
supabase db dump --project-id YOUR_PROJECT_ID --table sales --file "backups/sales_backup.sql"

# 사용자 데이터만
supabase db dump --project-id YOUR_PROJECT_ID --table users --file "backups/users_backup.sql"

# 여러 테이블
supabase db dump --project-id YOUR_PROJECT_ID --table sales --table users --file "backups/core_tables.sql"
```

## 🔄 복원 방법

### 🌐 Supabase 대시보드 (권장)
1. [Supabase Dashboard](https://app.supabase.com) 접속
2. 프로젝트 선택
3. **SQL Editor** 메뉴
4. **Upload SQL file** 또는 백업 파일 내용 복사/붙여넣기
5. **Run** 버튼 클릭

### 💻 PostgreSQL 클라이언트
```bash
# psql 사용 (연결 정보 필요)
psql "postgresql://postgres:[PASSWORD]@[HOST]:5432/postgres" -f "backups/backup_file.sql"

# pgAdmin 사용
# Tools > Query Tool > 파일 열기 > 실행
```

## ⚙️ 자동 백업 설정

### 📅 Windows 작업 스케줄러
1. **작업 스케줄러** 실행 (`taskschd.msc`)
2. **기본 작업 만들기**
3. **작업 이름**: "YKP ERP 일일 백업"
4. **트리거**: 매일 새벽 2시
5. **동작**: `D:\Project\ykp-dashboard\backups\backup_supabase.bat YOUR_PROJECT_ID`

### 🐧 Linux Cron (서버 환경)
```bash
# 매일 새벽 2시 자동 백업
0 2 * * * /usr/bin/supabase db dump --project-id YOUR_PROJECT_ID --file "/backups/auto_backup_$(date +\%Y\%m\%d).sql"
```

## 📁 백업 파일 관리

### 📂 파일 명명 규칙
```
full_backup_20250923_051500.sql     # 전체 백업
schema_backup_20250923_051500.sql   # 스키마만
sales_backup_20250923_051500.sql    # 판매 데이터만
users_backup_20250923_051500.sql    # 사용자 데이터만
```

### 🗂️ 백업 보관 정책
- **일일 백업**: 7일 보관
- **주간 백업**: 4주 보관
- **월간 백업**: 12개월 보관
- **연간 백업**: 영구 보관

### 🧹 자동 정리 스크립트
```bash
# 7일 이상 된 백업 파일 삭제
forfiles /p "D:\Project\ykp-dashboard\backups" /s /m *.sql /d -7 /c "cmd /c del @path"
```

## 🔐 보안 고려사항

### 🛡️ 백업 파일 암호화
```bash
# 7-Zip으로 압축 + 암호화
7z a -p"YOUR_PASSWORD" "backup_encrypted.7z" "backup_file.sql"

# GPG 암호화
gpg --symmetric --cipher-algo AES256 backup_file.sql
```

### 💾 원격 저장소 업로드
```bash
# Google Drive, OneDrive, Dropbox 등에 자동 업로드
# AWS S3, Azure Blob Storage 등 클라우드 스토리지 활용
```

## ⚠️ 주의사항

1. **백업 전 확인사항**
   - 디스크 공간 충분한지 확인
   - Supabase 로그인 상태 확인
   - 프로젝트 ID 정확성 확인

2. **복원 시 주의사항**
   - **기존 데이터가 완전히 삭제됩니다**
   - 복원 전 현재 상태 백업 권장
   - 테스트 환경에서 먼저 검증

3. **성능 고려사항**
   - 대용량 백업은 시간이 오래 걸림
   - 백업 중 데이터베이스 성능 저하 가능
   - 사용량이 적은 시간대에 백업 권장

## 🆘 문제 해결

### ❌ 로그인 오류
```bash
# 재로그인
supabase logout
supabase login
```

### ❌ 권한 오류
- Supabase 프로젝트의 **Owner** 또는 **Admin** 권한 필요
- API 키 권한 확인

### ❌ 백업 파일 손상
- 백업 직후 파일 무결성 검증
- 여러 백업본 유지
- 체크섬(MD5, SHA256) 생성 및 확인

## 📞 지원

문제가 발생하면:
1. [Supabase 공식 문서](https://supabase.com/docs)
2. [Supabase Discord](https://discord.supabase.com)
3. 백업 로그 파일 확인
4. 개발팀 문의