---
name: doc-sync
description: Keep project documentation (CLAUDE.md, .env.example) in sync with actual configuration changes. Use after major configuration changes, environment setup modifications, or when user requests "update documentation" or "sync docs with current setup".
allowed-tools: [Read, Grep]
---

# Documentation Sync

프로젝트 문서를 실제 설정과 동기화하여 일관성을 유지하는 Skill입니다.

## 🎯 자동 실행 트리거

다음 상황에서 **자동으로 실행**:
- 환경 설정 구조 변경 후
- 새로운 Skill 추가 후
- "문서 업데이트해줘" / "Update documentation"
- "CLAUDE.md에 반영해줘"
- ".env.example 최신화"

## 📋 동기화 대상 파일

### 1. CLAUDE.md (프로젝트 가이드)
- 환경 설정 방법
- 개발 명령어
- Skills 사용법
- 트러블슈팅 가이드

### 2. .env.example (로컬 개발 템플릿)
- 로컬 개발용 기본 설정
- 환경변수 예시
- 설명 주석

### 3. .env.production.example (프로덕션 템플릿)
- Railway 배포용 설정
- Supabase 연결 정보
- 보안 주의사항

## 🔍 불일치 감지

### CLAUDE.md 검증

**체크 항목**:
1. **환경 설정 섹션**:
   - `.env.local` 언급 → ❌ (사용 안함!)
   - 파일 드라이버 설명 → ✅ (최신)
   - Railway 환경변수 설명 → ✅

2. **Skills 섹션**:
   - 설치된 Skill 목록과 일치 → ✅
   - 각 Skill 설명 포함 → ✅

3. **Quick Reference Card**:
   - 최신 명령어 반영 → ✅
   - 환경 분리 항목 → ✅

### .env.example 검증

**체크 항목**:
1. **드라이버 설정**:
   ```env
   SESSION_DRIVER=file  # database 아님!
   CACHE_STORE=file     # database 아님!
   QUEUE_CONNECTION=sync # database 아님!
   ```

2. **DB 연결**:
   ```env
   DB_CONNECTION=pgsql_local  # pgsql 아님!
   DB_HOST_LOCAL=localhost
   DB_DATABASE_LOCAL=ykp_dashboard_local
   ```

3. **주석 설명**:
   - 각 설정의 목적 명시
   - 로컬 개발용임을 강조

## 📊 동기화 보고서

문서 동기화 완료 후 보고:

```
📝 문서 동기화 보고

✅ CLAUDE.md:
- Environment Configuration 섹션: 최신 ✅
- Available Skills 섹션: 5개 Skill 추가 ✅
- Quick Reference Card: 환경 분리 항목 추가 ✅

✅ .env.example:
- 파일 드라이버 설정: 업데이트 ✅
- 주석 설명: 추가 ✅
- 로컬 개발 강조: 추가 ✅

✅ .env.production.example:
- 변경 사항 없음 (최신 상태)

📋 변경 내역:
1. CLAUDE.md: 환경 분리 전략 업데이트
2. .env.example: 드라이버 설정 변경 (database → file)
3. Skills 섹션 신규 추가

💡 Git 커밋 제안:
```bash
git add CLAUDE.md .env.example .claude/skills/
git commit -m "📝 docs: update environment setup documentation

- Add environment separation strategy
- Update .env.example with file-based drivers
- Add 5 Claude Skills (env-health, env-switch, cache-guard, db-validate, doc-sync)
- Fix .env.local explanation (not supported in Laravel)

🤖 Generated with Claude Code
Co-Authored-By: Claude <noreply@anthropic.com>"
```
```

## 🛠️ 자동 문서 생성

### Skills 섹션 자동 생성

현재 설치된 Skills 기반으로 문서 생성:

```markdown
## Available Claude Skills

현재 프로젝트에는 다음 Skills가 설치되어 있습니다:

### 1. env-health (Environment Health Checker)
**자동 실행**: 환경 설정/캐시/DB 연결 문제 발생 시

환경 설정, 캐시, DB 연결 문제를 자동으로 진단하고 해결합니다.

**주요 기능**:
- `.env` 파일 일관성 체크
- Laravel 캐시 상태 진단
- DB 연결 테스트
- 자동 캐시 클리어

**사용 예시**:
"왜 Laravel이 SQLite로 연결하려고 해?"
→ env-health Skill 자동 실행하여 문제 진단 및 해결

---

### 2. env-switch (Environment Switcher)
**자동 실행**: 로컬/프로덕션 환경 전환 요청 시

로컬과 프로덕션 환경 간 안전한 전환을 가이드합니다.

**주요 기능**:
- 현재 환경 분석
- 목표 환경 설정 생성
- 위험 감지 (프로덕션 DB 접근 등)
- 단계별 전환 가이드

**사용 예시**:
"로컬 개발 환경으로 전환하고 싶어"
→ env-switch Skill이 단계별 가이드 제공

---

### 3. cache-guard (Laravel Cache Guardian)
**자동 실행**: .env 또는 config 파일 수정 직후

설정 변경을 감지하고 자동으로 캐시를 관리합니다.

**주요 기능**:
- 설정 파일 변경 감지
- 적절한 캐시 클리어
- 서버 재시작 안내
- 캐시 상태 모니터링

**사용 예시**:
[.env 파일 수정 후]
→ cache-guard가 자동으로 캐시 클리어 및 재시작 안내

---

### 4. db-validate (Database Connection Validator)
**자동 실행**: DB 연결 오류 발생 시

데이터베이스 설정을 검증하고 연결을 테스트합니다.

**주요 기능**:
- .env 파일 DB 설정 검증
- 실제 DB 연결 테스트
- PostgreSQL 서버 상태 확인
- 로컬/프로덕션 혼동 감지

**사용 예시**:
"Database hosts array is empty 오류"
→ db-validate가 문제 진단 및 해결책 제시

---

### 5. doc-sync (Documentation Sync)
**자동 실행**: 주요 설정 변경 후 문서 업데이트 요청 시

프로젝트 문서를 실제 설정과 동기화합니다.

**주요 기능**:
- CLAUDE.md 업데이트
- .env.example 동기화
- Skills 목록 자동 생성
- Git 커밋 메시지 제안

**사용 예시**:
"CLAUDE.md에 지금까지 변경사항 반영해줘"
→ doc-sync가 문서 동기화 및 커밋 메시지 생성
```

## 💡 문서 작성 베스트 프랙티스

### 1. 실제 경험 기반 작성
- ✅ 지금까지 겪은 문제를 문서화
- ✅ 시행착오를 "Troubleshooting" 섹션으로
- ✅ 해결책을 "Quick Reference"로

### 2. 구체적인 예시 포함
- ✅ 실제 명령어 (복사-붙여넣기 가능)
- ✅ 예상 출력 결과
- ✅ 오류 메시지와 해결 방법

### 3. 버전 정보 명시
- ✅ Laravel 버전
- ✅ PostgreSQL 버전
- ✅ Node/npm 버전
- ✅ Last Updated 날짜

### 4. 팀 공유 고려
- ✅ 다른 개발자가 읽어도 이해 가능
- ✅ AI (Claude/Cursor)도 이해 가능
- ✅ 검색 가능한 키워드 포함

## 🎯 문서화해야 할 중요 항목

### 꼭 문서화할 것

1. **환경 분리 전략** ⭐⭐⭐
   - 로컬 vs 프로덕션
   - 왜 `.env.local` 안 쓰는지
   - 파일 vs DB 드라이버 선택

2. **자주 발생하는 오류** ⭐⭐⭐
   - "Database hosts array is empty"
   - "SQLite connection attempt"
   - 캐시 문제

3. **설정 변경 후 필수 단계** ⭐⭐
   - 캐시 클리어
   - 서버 재시작
   - 검증 방법

4. **프로덕션 배포 체크리스트** ⭐⭐
   - Railway 환경변수 설정
   - Supabase 백업
   - 보안 검증

### 문서화 불필요

- ❌ Laravel 기본 문서에 있는 내용
- ❌ 한 번만 사용하는 임시 스크립트
- ❌ 개인 메모 (TODO 등)

## 🔄 문서 업데이트 트리거

### 자동 업데이트 필요

다음 변경 사항은 문서 업데이트 필수:

1. **환경 설정 구조 변경**
   - `.env` 파일 템플릿 변경
   - 새로운 환경변수 추가
   - 드라이버 변경 (database → file 등)

2. **새로운 Skill 추가**
   - Skills 목록에 추가
   - 사용법 설명
   - 트리거 조건 명시

3. **개발 워크플로우 변경**
   - 새로운 배포 절차
   - 테스트 방법 변경
   - Git 브랜치 전략 수정

4. **문제 해결 방법 발견**
   - 새로운 트러블슈팅 케이스
   - 효율적인 디버깅 방법
   - 자주 하는 실수 패턴

## 📚 문서 일관성 체크리스트

문서 업데이트 후 확인:

- [ ] CLAUDE.md 목차와 실제 내용 일치
- [ ] .env.example과 CLAUDE.md 설명 일치
- [ ] 모든 Bash 명령어 실행 가능
- [ ] 예시 출력이 최신 버전 반영
- [ ] "Last Updated" 날짜 업데이트
- [ ] 깨진 링크 없음
- [ ] 오타 및 문법 확인

## 🎓 학습 포인트

이 Skill을 통해 배운 핵심:

1. **좋은 문서의 조건**:
   - 실제 문제 기반
   - 구체적인 해결책
   - 복사 가능한 명령어
   - 검색 가능한 키워드

2. **문서는 살아있어야 함**:
   - 코드 변경 = 문서 업데이트
   - 문제 해결 = 트러블슈팅 추가
   - 새로운 패턴 = 베스트 프랙티스 반영

3. **팀원은 미래의 나**:
   - 6개월 후 내가 읽어도 이해 가능
   - 새로운 팀원이 읽어도 이해 가능
   - AI가 읽어도 올바른 가이드 제공

**문서 업데이트 습관**:
1. 중요한 변경 직후 문서화
2. 문제 해결 후 트러블슈팅 추가
3. 주기적 검토 (월 1회)
4. Git 커밋 메시지에 문서 변경 내역 포함
