# 🌿 Crypto Trading System - Git Branch Strategy

## 📋 브랜치 구조 개요

```
crypto 저장소 (https://github.com/mim1012/Crypto.git)
├── main                           # 🔒 프로덕션 브랜치 (보호됨)
├── develop                        # 🔄 개발 통합 브랜치
├── feature/                       # ✨ 기능 개발 브랜치들
│   ├── feature/core-engine        # 거래 엔진 개발
│   ├── feature/web-dashboard      # 웹 대시보드 개발
│   ├── feature/security-system    # 보안 시스템 개발
│   ├── feature/api-integration    # API 통합 개발
│   ├── feature/desktop-gui        # 데스크톱 GUI 개발
│   └── feature/testing-framework  # 테스트 프레임워크 개발
├── release/                       # 🚀 릴리즈 준비 브랜치들
│   └── release/v1.0.0            # 첫 번째 릴리즈 준비
├── hotfix/                        # 🚨 긴급 수정 브랜치들
└── docs/                         # 📚 문서화 브랜치들
    └── docs/api-documentation    # API 문서 업데이트
```

## 🎯 브랜치별 역할

### 📌 주요 브랜치

#### `main` 브랜치
- **목적**: 프로덕션 환경 배포용 브랜치
- **보호**: ✅ 직접 푸시 금지, PR만 허용
- **승인**: 2명 이상 코드 리뷰 필요
- **자동화**: 태그 시 자동 릴리즈 생성

#### `develop` 브랜치  
- **목적**: 개발 통합 및 테스트 브랜치
- **보호**: ✅ 직접 푸시 금지, PR만 허용  
- **승인**: 1명 이상 코드 리뷰 필요
- **자동화**: CI/CD 테스트 자동 실행

### 🔧 작업 브랜치

#### Feature 브랜치들
```bash
feature/core-engine              # Core Engine Agent 담당
feature/web-dashboard           # Web Dashboard Agent 담당  
feature/security-system         # Security Agent 담당
feature/api-integration         # API Integration Agent 담당
feature/desktop-gui             # Desktop GUI 개발
feature/testing-framework       # Testing 및 품질 보증
```

## 🚀 워크플로우

### 1. 새 기능 개발 시작
```bash
# develop 브랜치에서 시작
git checkout develop
git pull crypto develop

# 새 기능 브랜치 생성
git checkout -b feature/core-engine-trading-logic

# 개발 작업...
git add .
git commit -m "feat: implement trading engine core logic"

# 원격에 푸시
git push -u crypto feature/core-engine-trading-logic
```

### 2. Pull Request 생성
```bash
# GitHub에서 PR 생성 또는 CLI 사용
gh pr create --base develop --head feature/core-engine-trading-logic \
  --title "feat: Core Trading Engine Implementation" \
  --body "5가지 진입 조건 및 4가지 청산 방식 구현"
```

### 3. 코드 리뷰 및 병합
```bash
# 리뷰 승인 후 develop에 병합
git checkout develop
git pull crypto develop
git merge --no-ff feature/core-engine-trading-logic
git push crypto develop

# 기능 브랜치 정리
git branch -d feature/core-engine-trading-logic
git push crypto --delete feature/core-engine-trading-logic
```

### 4. 릴리즈 준비
```bash
# 릴리즈 브랜치 생성
git checkout develop
git pull crypto develop
git checkout -b release/v1.0.0

# 버전 업데이트
echo "1.0.0" > VERSION
git add VERSION
git commit -m "chore: bump version to 1.0.0"

# 릴리즈 브랜치 푸시
git push -u crypto release/v1.0.0
```

### 5. 프로덕션 배포
```bash
# main과 develop에 병합
git checkout main
git merge --no-ff release/v1.0.0
git tag -a v1.0.0 -m "Release v1.0.0"
git push crypto main --tags

git checkout develop
git merge --no-ff release/v1.0.0
git push crypto develop
```

## 🔐 브랜치 보호 규칙

### Main Branch 보호
- ✅ 직접 푸시 금지
- ✅ Pull Request 필수
- ✅ 2명 이상 리뷰 승인 필요
- ✅ CI/CD 테스트 통과 필수
- ✅ 관리자도 규칙 준수 필요

### Develop Branch 보호
- ✅ 직접 푸시 금지  
- ✅ Pull Request 필수
- ✅ 1명 이상 리뷰 승인 필요
- ✅ CI/CD 테스트 통과 필수

## 📊 현재 브랜치 상태

### ✅ 생성 완료된 브랜치
```bash
crypto/main                                    # 프로덕션 브랜치
crypto/develop                                 # 개발 통합 브랜치  
crypto/feature/crypto-system-initialization    # 초기 시스템 설정
```

### 📋 생성 예정 브랜치
```bash
feature/core-engine                  # 거래 엔진 구현
feature/security-system              # 보안 시스템 구현
feature/api-integration              # API 통합 구현
feature/web-dashboard                # 웹 대시보드 구현
feature/desktop-gui                  # 데스크톱 GUI 구현
```

## 🎪 서브에이전트별 브랜치 할당

| 서브에이전트 | 담당 브랜치 | 주요 작업 |
|------------|-----------|----------|
| **core-engine-agent** | `feature/core-engine` | 5가지 진입조건, 4가지 청산방식 |
| **security-specialist** | `feature/security-system` | Fernet 암호화, JWT 인증 |
| **api-integration-specialist** | `feature/api-integration` | 바이낸스/바이비트 API 연동 |
| **web-dashboard-specialist** | `feature/web-dashboard` | Flask 백엔드, 반응형 프론트엔드 |
| **github-manager** | `모든 브랜치` | Git 워크플로우, PR 관리, 릴리즈 |

## 🎯 브랜치 명명 규칙

### Feature 브랜치
```bash
feature/[component]-[description]
예: feature/core-engine-trading-logic
    feature/web-dashboard-realtime
    feature/security-jwt-auth
```

### Bugfix 브랜치
```bash
bugfix/[issue-number]-[description]  
예: bugfix/123-api-connection-timeout
    bugfix/456-memory-leak-fix
```

### Release 브랜치
```bash
release/[version]
예: release/v1.0.0
    release/v1.1.0-beta
```

### Hotfix 브랜치
```bash
hotfix/[version]-[critical-issue]
예: hotfix/v1.0.1-emergency-close-bug
    hotfix/v1.0.2-security-vulnerability
```

## 🚀 다음 단계

### 1. 브랜치 보호 규칙 설정
```bash
# GitHub 웹에서 Settings > Branches에서 설정
# 또는 GitHub CLI 사용 (설치 후)
```

### 2. 각 서브에이전트별 Feature 브랜치 생성
```bash
git checkout develop
git checkout -b feature/core-engine
git push -u crypto feature/core-engine

git checkout develop  
git checkout -b feature/security-system
git push -u crypto feature/security-system

# 나머지 브랜치들도 동일하게...
```

### 3. CI/CD 파이프라인 설정
- `.github/workflows/ci.yml` 생성
- 테스트 자동화 설정
- 코드 품질 검사 자동화

**이제 전문적인 Git 브랜치 전략으로 체계적인 개발이 가능합니다!** 🎉