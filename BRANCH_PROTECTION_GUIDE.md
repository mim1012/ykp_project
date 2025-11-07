# Branch Protection Guide (1-Person Dev + AI)

이 가이드는 1인 개발자가 Claude Code와 Cursor를 사용하는 환경에 최적화된 브랜치 보호 규칙을 설명합니다.

## 목차
1. [main 브랜치 보호 규칙 (간소화)](#main-브랜치-보호-규칙)
2. [CI/CD 자동화 설정](#cicd-자동화-설정)
3. [Auto-Merge 설정 (선택)](#auto-merge-설정)
4. [AI 도구 통합](#ai-도구-통합)

---

## main 브랜치 보호 규칙

### 🎯 핵심 원칙
- **PR 필수**: 기록 추적 목적
- **승인 불필요**: 1인 개발이므로 자가 승인
- **CI 통과 필수**: 품질 보장
- **Force push 금지**: 안전성 확보

### GitHub 설정 방법

#### 1. 브랜치 보호 규칙 추가
1. GitHub 리포지토리 → **Settings** 탭
2. 왼쪽 메뉴 → **Branches**
3. **Add branch protection rule** 클릭
4. **Branch name pattern**: `main` 입력

#### 2. 필수 설정 항목

**✅ Require a pull request before merging**
- **체크**: ✅ 활성화
- **Required approvals**: `0` ← **1인 개발 최적화**
- **Dismiss stale pull request approvals**: ⬜ 비활성화 (불필요)
- **Require review from Code Owners**: ⬜ 비활성화

**✅ Require status checks to pass before merging**
- **체크**: ✅ 활성화
- **Require branches to be up to date**: ⬜ 선택 사항 (유연성 위해 비활성화 권장)
- **Status checks that are required**:
  - ✅ `test` - PHPUnit 백엔드 테스트
  - ✅ `format-check` - Laravel Pint 코드 스타일
  - ⬜ `analyse` - PHPStan (선택, 빌드 시간 증가)
  - ⬜ `e2e-smoke` - Playwright (선택, 느림)

**⬜ Require conversation resolution before merging**
- **체크**: ⬜ **비활성화** ← 1인 개발이므로 불필요

**⬜ Include administrators**
- **체크**: ⬜ **비활성화** ← 긴급 상황 시 유연성 확보

**❌ Allow force pushes**
- **체크**: ❌ **비활성화** (안전성 목적)

**❌ Allow deletions**
- **체크**: ❌ **비활성화** (main 브랜치 삭제 방지)

---

## CI/CD 자동화 설정

### 최소 CI 파이프라인 (빠른 빌드)

#### `.github/workflows/ci.yml` (단일 파일)

```yaml
name: CI Pipeline

on:
  pull_request:
    branches: [main]
  push:
    branches: [main]

jobs:
  test:
    name: Backend Tests
    runs-on: ubuntu-latest
    timeout-minutes: 10

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP 8.2
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, xml, ctype, json, pdo, pdo_sqlite
          coverage: none

      - name: Cache Composer dependencies
        uses: actions/cache@v3
        with:
          path: vendor
          key: composer-${{ hashFiles('**/composer.lock') }}

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress --no-interaction

      - name: Run tests
        run: composer test

  format-check:
    name: Code Style Check
    runs-on: ubuntu-latest
    timeout-minutes: 5

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP 8.2
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Check code style
        run: composer format-check

  # 선택 사항: E2E 스모크 테스트 (느림, 필요 시 활성화)
  # e2e-smoke:
  #   name: E2E Smoke Tests
  #   runs-on: ubuntu-latest
  #   timeout-minutes: 15
  #   steps:
  #     - uses: actions/checkout@v4
  #     - name: Setup Node.js
  #       uses: actions/setup-node@v4
  #       with:
  #         node-version: '18'
  #     - run: npm ci
  #     - run: npx playwright install --with-deps
  #     - run: npm run test:smoke
```

### CI 최적화 팁

**빠른 빌드 전략**:
- ✅ **Composer 캐싱**: vendor 폴더 캐시로 설치 시간 단축
- ✅ **병렬 실행**: test와 format-check 동시 실행
- ✅ **타임아웃 설정**: 무한 대기 방지
- ⬜ **E2E 테스트**: 필요할 때만 활성화 (CI 시간 절약)

**비용 절감**:
- GitHub Actions: 월 2000분 무료 (개인 계정)
- 평균 빌드 시간: ~3분 (test + format-check)
- 예상 월 사용량: ~60분 (하루 1-2 PR 기준)

---

## Auto-Merge 설정

### 자동 병합으로 개발 속도 향상

#### 설정 방법

**1. GitHub Settings에서 auto-merge 활성화**
1. 리포지토리 → **Settings** → **General**
2. **Pull Requests** 섹션
3. ✅ **Allow auto-merge** 체크

**2. PR 생성 시 auto-merge 활성화**

```bash
# CLI로 PR 생성 + auto-merge 활성화
gh pr create --title "feat: add feature" --body "Description" --base main --auto-merge
```

**또는 GitHub 웹에서**:
1. PR 생성 후
2. **Enable auto-merge** 버튼 클릭
3. **Merge method** 선택: **Squash and merge** 권장

#### Auto-Merge 흐름
```
1. 브랜치 push → PR 자동 생성
2. CI 테스트 시작
3. CI 통과 ✅
4. 자동으로 main에 merge
5. 브랜치 자동 삭제
```

#### 장단점

**장점**:
- ⚡ 개발 속도 향상 (수동 merge 불필요)
- 🤖 CI 통과 확인만 하면 자동 배포
- 🧹 브랜치 자동 정리

**단점**:
- ⚠️ AI가 생성한 코드를 바로 merge (리뷰 없음)
- 🐛 버그 발견 시 revert 필요

**권장**:
- ✅ 사용 (1인 개발 + AI 리뷰로 충분)
- 단, 중요한 변경(DB 마이그레이션, 보안)은 수동 확인

---

## AI 도구 통합

### Claude Code와의 협업

#### 자동 커밋 메시지 생성
```bash
# Claude Code가 자동으로 커밋 메시지 생성
🤖 feat(sales): add bulk import via CSV
🤖 fix(dashboard): correct chart date range
```

#### PR 생성 시 Claude에게 리뷰 요청
```bash
# PR 생성 후 Claude Code에게 요청
"Please review the code in claude/sales-feature:
- Check for bugs
- Verify test coverage
- Suggest improvements"
```

### Cursor와의 협업

#### Cursor Rules 자동 적용
`.cursorrules` 파일이 있으므로 Cursor가 자동으로:
- `cursor/*` 브랜치만 사용
- 🔮 이모지로 커밋
- 프론트엔드 작업에만 집중

#### Cross-Review
```bash
# Cursor 브랜치를 Claude에게 리뷰 요청
git checkout cursor/ui-improvements
# Claude Code에서: "Review this branch for backend integration"
```

---

## 워크플로우 예시

### Claude Code 작업 (claude/*)

```bash
# 1. 브랜치 생성
git checkout main && git pull
git checkout -b claude/sales-bulk-import

# 2. Claude Code로 개발 (자동 커밋)
# Claude가 자동으로 커밋 메시지 생성

# 3. PR 생성 + Auto-merge 활성화
gh pr create \
  --title "feat(sales): add bulk CSV import" \
  --body "Implements CSV parsing and validation" \
  --base main \
  --auto-merge

# 4. CI 통과 시 자동 merge
# 5. 로컬 브랜치 정리
git checkout main && git pull
git branch -d claude/sales-bulk-import
```

### Cursor 작업 (cursor/*)

```bash
# 1. 브랜치 생성
git checkout main && git pull
git checkout -b cursor/ui-responsive

# 2. Cursor Composer로 UI 작업
# Cursor가 .cursorrules 규칙 따름

# 3. 커밋 (🔮 이모지 필수)
git commit -m "🔮 feat(ui): add responsive layout"

# 4. PR 생성
gh pr create \
  --title "feat(ui): responsive design" \
  --body "Mobile-first responsive layout" \
  --base main \
  --auto-merge

# 5. 자동 merge 후 정리
git checkout main && git pull
git branch -d cursor/ui-responsive
```

### 긴급 수정 (feature/hotfix-*)

```bash
# 1. 긴급 브랜치 생성
git checkout main
git checkout -b feature/hotfix-critical-bug

# 2. 즉시 수정
git commit -m "🚨 hotfix: fix critical bug"

# 3. CI 통과 확인 후 수동 merge (신중하게)
gh pr create \
  --title "HOTFIX: Critical bug" \
  --body "Emergency fix, needs immediate merge" \
  --base main

# 4. GitHub에서 수동 merge (auto-merge 사용 안 함)
# 5. 배포 확인 후 브랜치 삭제
```

---

## 문제 해결 (Troubleshooting)

### Q1: CI가 계속 실패함
**해결책**:
```bash
# 로컬에서 먼저 테스트
composer test          # 백엔드 테스트
composer format-check  # 코드 스타일 검사

# 문제 수정 후 다시 push
git add .
git commit -m "fix: resolve CI failures"
git push
```

### Q2: Auto-merge가 작동하지 않음
**원인 1**: CI가 아직 실행 중
- **해결**: CI 완료까지 대기 (약 3-5분)

**원인 2**: Auto-merge 설정 안 함
- **해결**: PR 페이지에서 "Enable auto-merge" 클릭

**원인 3**: Branch protection에서 auto-merge 비활성화
- **해결**: Settings → General → Allow auto-merge 체크

### Q3: 실수로 main에 잘못된 코드가 merge됨
**해결책**:
```bash
# 1. 최근 커밋 revert
git revert HEAD
git push origin main

# 2. 또는 특정 커밋 revert
git revert <commit-hash>
git push origin main
```

### Q4: Claude와 Cursor가 같은 파일 수정해서 충돌
**예방**:
- Claude: 백엔드 파일 (`app/*`, `database/*`, `routes/*`)
- Cursor: 프론트엔드 파일 (`resources/js/*`)
- 역할 분리가 핵심!

**충돌 발생 시**:
```bash
# main을 먼저 merge한 브랜치로 전환
git checkout cursor/ui-feature

# main의 최신 변경사항 가져오기
git merge main

# 충돌 해결 후
git add .
git commit -m "🔮 fix: resolve merge conflict"
git push
```

---

## 체크리스트

### ✅ 초기 설정 (한 번만)
- [ ] GitHub Settings → Branches → main 보호 규칙 설정
- [ ] `.github/workflows/ci.yml` 파일 생성
- [ ] `.cursorrules` 파일 생성 (이미 완료)
- [ ] Settings → General → Allow auto-merge 활성화
- [ ] CLAUDE.md에 브랜치 전략 문서화 (이미 완료)

### ✅ 매 작업 시
- [ ] `claude/*` 또는 `cursor/*` 브랜치 생성
- [ ] 적절한 이모지로 커밋 (🤖/🔮)
- [ ] PR 생성 시 --auto-merge 플래그 사용
- [ ] CI 통과 확인
- [ ] Merge 후 로컬 브랜치 정리

### ✅ 주기적 점검 (월 1회)
- [ ] 오래된 브랜치 정리 (2주 이상)
- [ ] CI 실패 로그 확인 및 개선
- [ ] GitHub Actions 사용량 확인
- [ ] CLAUDE.md 문서 업데이트

---

## 추가 자료

- [GitHub Branch Protection Docs](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-protected-branches/about-protected-branches)
- [GitHub Actions Docs](https://docs.github.com/en/actions)
- [GitHub Auto-Merge Docs](https://docs.github.com/en/pull-requests/collaborating-with-pull-requests/incorporating-changes-from-a-pull-request/automatically-merging-a-pull-request)
- [프로젝트 브랜치 전략 (CLAUDE.md)](./CLAUDE.md#git-workflow--branching-strategy)
- [Cursor Rules (.cursorrules)](./.cursorrules)

---

**문서 작성일**: 2025-11-02
**최종 업데이트**: 2025-11-02 (1인 개발 환경 최적화)
**작성자**: Claude Code Assistant
