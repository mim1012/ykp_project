# Branch Protection Rules Setup Guide

이 가이드는 GitHub에서 YKP Dashboard 프로젝트의 브랜치 보호 규칙을 설정하는 방법을 설명합니다.

## 목차
1. [main 브랜치 보호 규칙](#main-브랜치-보호-규칙)
2. [develop 브랜치 보호 규칙](#develop-브랜치-보호-규칙)
3. [CI/CD Status Checks 설정](#cicd-status-checks-설정)
4. [팀원 역할 및 권한](#팀원-역할-및-권한)

---

## main 브랜치 보호 규칙

### GitHub 설정 경로
1. 리포지토리 → **Settings** 탭
2. 왼쪽 메뉴 → **Branches**
3. **Add branch protection rule** 클릭
4. **Branch name pattern**: `main` 입력

### 필수 설정 항목

#### ✅ Require a pull request before merging
- **체크**: ✅ 활성화
- **Required approvals**: `1` (최소 1명의 승인 필요)
- **Dismiss stale pull request approvals when new commits are pushed**: ✅ 체크 (새 커밋 푸시 시 이전 승인 무효화)
- **Require review from Code Owners**: ⬜ 선택 사항 (CODEOWNERS 파일이 있을 경우 활성화)

#### ✅ Require status checks to pass before merging
- **체크**: ✅ 활성화
- **Require branches to be up to date before merging**: ✅ 체크
- **Status checks that are required** (추후 CI/CD 설정 후 추가):
  - `test` - PHPUnit 테스트
  - `analyse` - PHPStan 정적 분석
  - `format-check` - Laravel Pint 코드 스타일 검사
  - `e2e-smoke` - Playwright 스모크 테스트

#### ✅ Require conversation resolution before merging
- **체크**: ✅ 활성화 (모든 코드 리뷰 대화가 해결되어야 merge 가능)

#### ✅ Include administrators
- **체크**: ✅ 활성화 (관리자도 동일한 규칙 적용)

#### ❌ Allow force pushes
- **체크**: ❌ **비활성화** (force push 금지)

#### ❌ Allow deletions
- **체크**: ❌ **비활성화** (브랜치 삭제 금지)

---

## develop 브랜치 보호 규칙

### GitHub 설정 경로
1. 리포지토리 → **Settings** 탭
2. 왼쪽 메뉴 → **Branches**
3. **Add branch protection rule** 클릭
4. **Branch name pattern**: `develop` 입력

### 필수 설정 항목

#### ✅ Require a pull request before merging
- **체크**: ✅ 활성화
- **Required approvals**: `1` (최소 1명의 승인 필요)
- **Dismiss stale pull request approvals when new commits are pushed**: ✅ 체크

#### ✅ Require status checks to pass before merging
- **체크**: ✅ 활성화
- **Require branches to be up to date before merging**: ⬜ 선택 사항 (develop은 빠른 통합을 위해 유연하게)
- **Status checks that are required**:
  - `test` - PHPUnit 테스트
  - `format-check` - 코드 스타일 검사

#### ✅ Require conversation resolution before merging
- **체크**: ✅ 활성화

#### ⬜ Include administrators
- **체크**: ⬜ 선택 사항 (develop은 관리자 예외 허용 가능)

#### ❌ Allow force pushes
- **체크**: ❌ **비활성화**

#### ❌ Allow deletions
- **체크**: ❌ **비활성화**

---

## CI/CD Status Checks 설정

브랜치 보호에 필요한 GitHub Actions 워크플로우를 설정합니다.

### 1. Main Branch CI/CD (`main.yml`)

`.github/workflows/main.yml` 파일 생성:

```yaml
name: Production CI/CD

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  test:
    name: Backend Tests
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP 8.2
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, xml, ctype, json, pdo, pdo_sqlite
          coverage: none

      - name: Install Composer dependencies
        run: composer install --prefer-dist --no-progress

      - name: Run PHPUnit tests
        run: composer test

  analyse:
    name: Static Analysis
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP 8.2
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Run PHPStan
        run: composer analyse

  format-check:
    name: Code Style Check
    runs-on: ubuntu-latest

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

  e2e-smoke:
    name: E2E Smoke Tests
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '18'

      - name: Install dependencies
        run: npm ci

      - name: Install Playwright
        run: npx playwright install --with-deps

      - name: Run smoke tests
        run: npm run test:smoke
```

### 2. Develop Branch CI (`develop.yml`)

`.github/workflows/develop.yml` 파일 생성:

```yaml
name: Development CI

on:
  push:
    branches: [develop]
  pull_request:
    branches: [develop]

jobs:
  test:
    name: Backend Tests
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP 8.2
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, xml, ctype, json, pdo, pdo_sqlite

      - name: Install dependencies
        run: composer install

      - name: Run tests
        run: composer test

  format-check:
    name: Code Style Check
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP 8.2
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install dependencies
        run: composer install

      - name: Check code style
        run: composer format-check
```

---

## 팀원 역할 및 권한

### Repository Roles

#### 1. Admin (관리자)
- **권한**: 모든 설정 변경, 브랜치 보호 규칙 우회 불가 (Include administrators 활성화 시)
- **대상**: 프로젝트 소유자, 시니어 개발자
- **책임**:
  - 브랜치 보호 규칙 관리
  - 릴리즈 관리
  - 긴급 핫픽스 승인

#### 2. Maintainer (유지보수자)
- **권한**: 코드 리뷰, PR 승인, 브랜치 merge
- **대상**: 시니어/중급 개발자
- **책임**:
  - PR 코드 리뷰 및 승인
  - 기술적 의사결정 참여
  - 품질 관리

#### 3. Write (개발자)
- **권한**: 코드 push, PR 생성, 이슈 관리
- **대상**: 모든 개발자
- **책임**:
  - 기능 개발
  - 버그 수정
  - 테스트 작성

#### 4. Read (읽기 전용)
- **권한**: 코드 조회만 가능
- **대상**: 외부 협력자, 감사자

---

## 설정 검증 체크리스트

### ✅ main 브랜치 보호 완료 확인
- [ ] PR 필수 (1명 승인)
- [ ] Status checks 필수 (test, analyse, format-check, e2e-smoke)
- [ ] 대화 해결 필수
- [ ] 관리자도 규칙 적용
- [ ] Force push 금지
- [ ] 브랜치 삭제 금지

### ✅ develop 브랜치 보호 완료 확인
- [ ] PR 필수 (1명 승인)
- [ ] Status checks 필수 (test, format-check)
- [ ] 대화 해결 필수
- [ ] Force push 금지
- [ ] 브랜치 삭제 금지

### ✅ CI/CD 파이프라인 설정 완료
- [ ] `.github/workflows/main.yml` 생성
- [ ] `.github/workflows/develop.yml` 생성
- [ ] GitHub Actions 실행 확인

### ✅ 팀원 권한 설정 완료
- [ ] Admin 역할 할당
- [ ] Maintainer 역할 할당
- [ ] Write 역할 할당 (모든 개발자)

---

## 문제 해결 (Troubleshooting)

### Q1: Status check가 Required checks 목록에 나타나지 않음
**해결책**: GitHub Actions 워크플로우를 먼저 실행해야 합니다. PR을 한 번 생성하면 status check가 목록에 나타납니다.

### Q2: 관리자가 PR 없이 merge하고 싶음
**해결책**: "Include administrators" 체크를 해제하면 관리자는 규칙을 우회할 수 있습니다. 하지만 **권장하지 않습니다** (보안 및 품질 관리 목적).

### Q3: CI/CD 파이프라인이 실패하는데 merge하고 싶음
**해결책**:
1. **권장**: CI 실패 원인을 수정하고 다시 push
2. **임시**: Repository Settings → Branch protection rule에서 해당 status check 제거 (merge 후 다시 활성화)

### Q4: develop 브랜치가 main보다 뒤처짐
**해결책**:
```bash
git checkout develop
git merge main
git push origin develop
```

---

## 추가 자료

- [GitHub Branch Protection 공식 문서](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/defining-the-mergeability-of-pull-requests/about-protected-branches)
- [GitHub Actions 공식 문서](https://docs.github.com/en/actions)
- [프로젝트 브랜치 전략 (CLAUDE.md)](./CLAUDE.md#git-workflow--branching-strategy)

---

**문서 작성일**: 2025-11-02
**최종 업데이트**: 2025-11-02
**작성자**: Claude Code Assistant
