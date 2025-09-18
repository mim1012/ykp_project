# Git Branching Strategy

## 브랜치 구조

```
main (production)
├── staging
│   └── develop
│       ├── feature/user-authentication
│       ├── feature/sales-dashboard
│       └── feature/api-improvements
├── hotfix/critical-bug-fix
└── release/v1.3.0
```

## 브랜치 설명

### 🔴 **main** (Production)
- 운영 환경에 배포되는 브랜치
- 직접 커밋 금지 (PR only)
- 자동 배포 트리거
- 보호 규칙 적용

### 🟠 **staging**
- 스테이징 환경 테스트 브랜치
- develop에서 PR을 통해 병합
- Railway 스테이징 자동 배포
- QA 테스트 수행

### 🟡 **develop**
- 개발 통합 브랜치
- 모든 feature 브랜치가 병합되는 곳
- CI 테스트 자동 실행
- 일일 빌드

### 🟢 **feature/**
- 새로운 기능 개발
- develop에서 분기
- 완료 후 develop으로 PR
- 명명 규칙: `feature/기능명-이슈번호`

### 🔵 **hotfix/**
- 긴급 버그 수정
- main에서 직접 분기
- main과 develop에 모두 병합
- 명명 규칙: `hotfix/이슈설명-날짜`

### 🟣 **release/**
- 릴리스 준비 브랜치
- develop에서 분기
- 버그 수정만 허용
- 명명 규칙: `release/v버전번호`

## Git Flow 명령어

### 초기 설정
```bash
# 브랜치 생성
git checkout -b develop
git checkout -b staging

# 원격 저장소에 푸시
git push -u origin develop
git push -u origin staging
```

### Feature 브랜치 작업
```bash
# Feature 브랜치 생성
git checkout develop
git checkout -b feature/new-feature

# 작업 후 커밋
git add .
git commit -m "feat: Add new feature"

# develop에 병합 (PR 권장)
git checkout develop
git merge feature/new-feature
git branch -d feature/new-feature
```

### Hotfix 작업
```bash
# Hotfix 브랜치 생성
git checkout main
git checkout -b hotfix/critical-fix

# 수정 후 커밋
git add .
git commit -m "fix: Critical bug fix"

# main과 develop에 병합
git checkout main
git merge hotfix/critical-fix

git checkout develop
git merge hotfix/critical-fix

git branch -d hotfix/critical-fix
```

### Release 작업
```bash
# Release 브랜치 생성
git checkout develop
git checkout -b release/v1.3.0

# 버전 업데이트
npm version minor
git add .
git commit -m "chore: Bump version to v1.3.0"

# main과 develop에 병합
git checkout main
git merge release/v1.3.0
git tag -a v1.3.0 -m "Release version 1.3.0"

git checkout develop
git merge release/v1.3.0

git branch -d release/v1.3.0
git push --tags
```

## 커밋 메시지 규칙

### 형식
```
<type>(<scope>): <subject>

<body>

<footer>
```

### Type
- `feat`: 새로운 기능
- `fix`: 버그 수정
- `docs`: 문서 변경
- `style`: 코드 포맷팅
- `refactor`: 코드 리팩토링
- `test`: 테스트 추가/수정
- `chore`: 빌드/설정 변경

### 예시
```bash
git commit -m "feat(auth): Add two-factor authentication

Implemented TOTP-based 2FA for enhanced security
- Added QR code generation
- Created verification endpoint
- Updated user model

Closes #123"
```

## GitHub 브랜치 보호 규칙

### main 브랜치
- ✅ Require pull request reviews (2 approvals)
- ✅ Dismiss stale pull request approvals
- ✅ Require status checks to pass
- ✅ Require branches to be up to date
- ✅ Include administrators
- ✅ Restrict who can push

### staging 브랜치
- ✅ Require pull request reviews (1 approval)
- ✅ Require status checks to pass
- ✅ Require branches to be up to date

### develop 브랜치
- ✅ Require status checks to pass
- ✅ Require linear history

## 자동화 워크플로우

### PR 생성 시
1. 자동 테스트 실행
2. 코드 품질 검사
3. 커버리지 리포트
4. Preview 배포 (Vercel/Netlify)

### 병합 시
- **develop → staging**: 스테이징 자동 배포
- **staging → main**: 운영 자동 배포 (승인 필요)

## 버전 관리

### Semantic Versioning
```
MAJOR.MINOR.PATCH

1.0.0 → 2.0.0 (Breaking changes)
1.0.0 → 1.1.0 (New features)
1.0.0 → 1.0.1 (Bug fixes)
```

### 태그 생성
```bash
git tag -a v1.3.0 -m "Release version 1.3.0"
git push origin v1.3.0
```

## 팀 협업 규칙

1. **모든 작업은 이슈 기반**
   - GitHub Issues에 작업 등록
   - 브랜치명에 이슈 번호 포함

2. **코드 리뷰 필수**
   - 최소 1명 이상 리뷰
   - CI 통과 필수

3. **일일 동기화**
   ```bash
   git checkout develop
   git pull origin develop
   git checkout feature/my-feature
   git rebase develop
   ```

4. **충돌 해결**
   - Rebase 우선
   - 필요시 Merge 사용
   - 팀과 소통

## 문제 해결

### 잘못된 브랜치에 커밋한 경우
```bash
# 커밋 복사
git cherry-pick <commit-hash>

# 원래 브랜치에서 제거
git reset --hard HEAD~1
```

### 병합 충돌 해결
```bash
git merge develop
# 충돌 파일 수정
git add .
git merge --continue
```

### 브랜치 정리
```bash
# 로컬 브랜치 정리
git branch --merged | grep -v "\*\|main\|develop" | xargs -n 1 git branch -d

# 원격 브랜치 정리
git remote prune origin
```