# Claude Skills Guide

이 프로젝트에는 환경 설정 및 트러블슈팅을 자동화하는 **5개의 Claude Skills**가 설치되어 있습니다.

Skills는 **자동으로 실행**됩니다 - 사용자가 특정 문제를 언급하면 Claude가 관련 Skill을 자동으로 활성화합니다.

## Skill 1: env-health (Environment Health Checker) ⭐⭐⭐

**자동 실행 조건**:
- "설정이 반영 안돼" 또는 "configuration not applied" 언급
- "Database connection error", "SQLite connection attempt" 오류
- "Database hosts array is empty" 오류
- ".env 파일 수정했는데 변화 없음"

**주요 기능**:
- 환경 설정 (`.env`) 일관성 검증
- Laravel 캐시 상태 진단 및 자동 클리어
- DB 연결 테스트 (pgsql_local 확인)
- Session/Cache 드라이버 문제 감지

**해결하는 문제**:
- ✅ 캐시 때문에 설정 변경 무시됨
- ✅ 잘못된 DB 연결 (SQLite, Supabase 등)
- ✅ "Database hosts array is empty" 오류

**사용 예시**:
```
사용자: "왜 Laravel이 SQLite로 연결하려고 해?"
Claude: [env-health Skill 자동 실행]
         → .env 파일 분석
         → 캐시 상태 확인
         → 문제 발견: "config.php 캐시 파일 존재"
         → php artisan optimize:clear 실행
         → 서버 재시작 안내
```

---

## Skill 2: env-switch (Environment Switcher) ⭐⭐

**자동 실행 조건**:
- "로컬로 전환" / "Switch to local"
- "프로덕션으로 전환" / "Switch to production"
- "환경 분리" / "Separate environments"
- "주석 처리 말고 자동으로"

**주요 기능**:
- 현재 환경 분석 (APP_ENV, DB_CONNECTION)
- 목표 환경 설정 생성 및 검증
- 위험 감지 (프로덕션 DB 접근 시도 등)
- 단계별 전환 가이드 제공

**해결하는 문제**:
- ✅ 로컬/프로덕션 환경 수동 전환 번거로움
- ✅ 실수로 프로덕션 DB 접근 방지
- ✅ 올바른 환경 설정 템플릿 제공

**사용 예시**:
```
사용자: "로컬 개발 환경으로 전환하고 싶어"
Claude: [env-switch Skill 자동 실행]
         → 현재 환경 확인 (production 감지)
         → ⚠️ 경고: 프로덕션 설정 사용 중!
         → 로컬 설정 템플릿 제공
         → 후속 조치 안내 (캐시 클리어, 서버 재시작)
```

---

## Skill 3: cache-guard (Laravel Cache Guardian) ⭐⭐

**자동 실행 조건**:
- `.env` 파일 수정 직후
- `config/*.php` 파일 수정 직후
- "설정이 반영 안돼" / "Changes not applied"
- "캐시 때문인가?"

**주요 기능**:
- 설정 파일 변경 감지
- 적절한 캐시 클리어 (config, cache, route, view)
- 서버 재시작 필요 여부 판단
- 캐시 상태 모니터링 및 보고

**해결하는 문제**:
- ✅ 설정 변경 후 캐시 클리어 깜빡함
- ✅ 서버 재시작 필요성 인지 못함
- ✅ 오래된 캐시 파일 사용 중

**사용 예시**:
```
[.env 파일 수정 후]
Claude: [cache-guard Skill 자동 실행]
         → 캐시 파일 존재 감지 (config.php)
         → php artisan config:clear 자동 실행
         → "서버 재시작 권장" 메시지 출력
         → 검증: 현재 설정 재확인
```

---

## Skill 4: db-validate (Database Connection Validator) ⭐

**자동 실행 조건**:
- "Database connection error" 오류
- "Database hosts array is empty" 오류
- "Could not connect to database" 오류
- "SQLSTATE[HY000]" 오류
- "DB 연결 확인해줘"

**주요 기능**:
- `.env` 파일 DB 설정 검증
- 실제 DB 연결 테스트 (tinker 사용)
- PostgreSQL 서버 상태 확인 (로컬)
- 로컬/프로덕션 DB 혼동 감지

**해결하는 문제**:
- ✅ DB 연결 설정 오류 진단
- ✅ PostgreSQL 서버 미실행 감지
- ✅ 비밀번호 불일치 또는 DB 미생성
- ✅ 프로덕션 DB 실수 접근 방지

**사용 예시**:
```
사용자: "Database hosts array is empty 오류 나는데?"
Claude: [db-validate Skill 자동 실행]
         → .env 파일 분석
         → SESSION_DRIVER=database 발견
         → SESSION_CONNECTION 없음 감지!
         → 권장: SESSION_DRIVER=file (로컬 개발)
         → 해결책 제시
```

---

## Skill 5: doc-sync (Documentation Sync) ⭐

**자동 실행 조건**:
- 주요 설정 변경 후
- 새로운 Skill 추가 후
- "문서 업데이트해줘" / "Update documentation"
- "CLAUDE.md에 반영해줘"
- ".env.example 최신화"

**주요 기능**:
- CLAUDE.md 업데이트 (환경 설정, Skills 목록)
- `.env.example` 템플릿 동기화
- 변경 내역 요약
- Git 커밋 메시지 자동 생성

**해결하는 문제**:
- ✅ 문서와 실제 설정 불일치
- ✅ 새로운 Skill 문서화 누락
- ✅ 커밋 메시지 작성 번거로움

**사용 예시**:
```
사용자: "CLAUDE.md에 지금까지 변경사항 반영해줘"
Claude: [doc-sync Skill 자동 실행]
         → 환경 설정 섹션 업데이트
         → Skills 목록 추가
         → Git 커밋 메시지 생성
         → 변경 내역 요약 보고
```

---

## Skills 사용 팁

### 자동 실행 vs 수동 호출

**Skills는 자동 실행됩니다** - 특별한 명령어 필요 없음!
```
❌ 잘못된 사용: "/env-health 실행해줘"
✅ 올바른 사용: "설정이 반영 안되는데?"
                → env-health Skill 자동 실행됨
```

### Skills 조합 사용

여러 Skills가 함께 작동할 수 있습니다:
```
1. .env 파일 수정
   → cache-guard: 캐시 클리어
   → env-health: 설정 검증
   → db-validate: DB 연결 테스트

2. 환경 전환
   → env-switch: 전환 가이드
   → cache-guard: 캐시 관리
   → doc-sync: 문서 업데이트
```

### Skills 위치

```
.claude/skills/
├── env-health/SKILL.md       # 환경 건강 진단
├── env-switch/SKILL.md       # 환경 전환
├── cache-guard/SKILL.md      # 캐시 관리
├── db-validate/SKILL.md      # DB 연결 검증
└── doc-sync/SKILL.md         # 문서 동기화
```

**팀 공유**: `.claude/skills/` 디렉토리가 Git에 포함되어 있어, 팀원들도 자동으로 사용 가능합니다.

---

**Back to**: [Development Docs Index](README.md) | [CLAUDE.md](../../CLAUDE.md)
