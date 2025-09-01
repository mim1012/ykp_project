# 📋 수동 설정 체크리스트

## 🛡️ 1. GitHub 브랜치 보호 규칙 설정

### 📍 접속: https://github.com/mim1012/Crypto/settings/branches

#### Main 브랜치 보호 설정
```
✅ 체크해야 할 항목들:

□ Restrict pushes that create files
□ Require a pull request before merging
  □ Require approvals: 2
  □ Dismiss stale PR approvals when new commits are pushed
  □ Require review from code owners
□ Require status checks to pass before merging
  □ Require branches to be up to date before merging
  □ Status checks that are required:
    - ci/tests
    - ci/security-scan
    - ci/performance-test
□ Require conversation resolution before merging
□ Include administrators (권장: 체크 해제)
```

#### Develop 브랜치 보호 설정
```
✅ 체크해야 할 항목들:

□ Restrict pushes that create files
□ Require a pull request before merging
  □ Require approvals: 1
  □ Dismiss stale PR approvals when new commits are pushed
□ Require status checks to pass before merging
  □ Status checks that are required:
    - ci/tests
    - ci/lint
□ Require conversation resolution before merging
```

---

## 🔑 2. GitHub Personal Access Token 생성

### 📍 접속: https://github.com/settings/tokens

```
✅ 생성해야 할 토큰:

Token Name: Crypto-Trading-System-CLI
Expiration: 90 days
Scopes 선택:
□ repo (Full control of private repositories)
□ workflow (Update GitHub Action workflows)
□ write:packages (Upload packages to GitHub Package Registry)
□ delete:packages (Delete packages from GitHub Package Registry)
```

**생성된 토큰을 안전한 곳에 저장하세요!**

---

## 📊 3. GitHub Repository 설정 확인

### 📍 접속: https://github.com/mim1012/Crypto/settings

#### General 설정
```
✅ 확인/설정해야 할 항목들:

□ Repository name: Crypto
□ Description: "고도로 정교한 암호화폐 자동매매 시스템 (듀얼 버전)"
□ Website: (선택사항)
□ Topics 추가: 
  - cryptocurrency
  - trading
  - python
  - flask
  - pyqt5
  - binance
  - bybit
  - automated-trading
□ Include in the home page: ✓
```

#### Features 설정
```
□ Wikis: ✓ (문서화용)
□ Issues: ✓ (버그 추적용)
□ Sponsorships: ✗
□ Preserve this repository: ✓
□ Projects: ✓ (프로젝트 관리용)
□ Discussions: ✓ (커뮤니티용)
```

#### Pull Requests 설정
```
□ Allow merge commits: ✓
□ Allow squash merging: ✓
□ Allow rebase merging: ✗
□ Always suggest updating pull request branches: ✓
□ Allow auto-merge: ✓
□ Automatically delete head branches: ✓
```

---

## 🤖 4. GitHub Actions Secrets 설정

### 📍 접속: https://github.com/mim1012/Crypto/settings/secrets/actions

```
✅ 추가해야 할 Secrets:

□ BINANCE_API_KEY: (실제 거래시 추가)
□ BINANCE_SECRET_KEY: (실제 거래시 추가)
□ BYBIT_API_KEY: (실제 거래시 추가)
□ BYBIT_SECRET_KEY: (실제 거래시 추가)
□ CODECOV_TOKEN: (코드 커버리지용)
□ DOCKER_USERNAME: (Docker Hub 배포용)
□ DOCKER_PASSWORD: (Docker Hub 배포용)
```

---

## 📈 5. 프로젝트 보드 생성 (선택사항)

### 📍 접속: https://github.com/mim1012/Crypto/projects

```
✅ 프로젝트 보드 설정:

Project Name: "Crypto Trading System Development"
Template: "Automated kanban with reviews"

Columns:
□ 📋 Backlog
□ 🔄 In Progress  
□ 👀 In Review
□ ✅ Done
□ 🚀 Released
```

---

## ⚙️ 6. 로컬 개발 환경 확인

### Git 설정 확인
```bash
# 터미널에서 실행:
git config --global user.name "Your Name"
git config --global user.email "your.email@example.com"

# 현재 설정 확인
git config --list
```

### 원격 저장소 연결 확인
```bash
# 현재 디렉토리에서 실행:
git remote -v

# 다음과 같이 나와야 함:
# crypto	https://github.com/mim1012/Crypto.git (fetch)
# crypto	https://github.com/mim1012/Crypto.git (push)
```

---

## 🔍 7. 확인 완료 체크리스트

### GitHub 웹사이트 확인 사항
```
□ Repository 생성 확인: https://github.com/mim1012/Crypto
□ README.md 표시 확인
□ 브랜치 3개 존재 확인 (main, develop, feature/crypto-system-initialization)
□ 파일들이 올바르게 업로드되었는지 확인:
  - .claude/agents/ (6개 파일)
  - documents/ (5개 파일)  
  - README.md
  - .gitignore
  - BRANCH_STRATEGY.md
  - MANUAL_SETUP_CHECKLIST.md (이 파일)
```

### 브랜치 구조 확인
```
□ main 브랜치가 기본 브랜치로 설정되어 있는지
□ 모든 파일이 올바르게 커밋되었는지
□ 커밋 메시지가 전문적으로 작성되었는지
```

---

## 🚀 8. 다음 단계 액션 플랜

### 즉시 실행 가능한 명령어들
```bash
# 1. 새 기능 브랜치 생성 예시
git checkout develop
git pull crypto develop
git checkout -b feature/core-engine-implementation
git push -u crypto feature/core-engine-implementation

# 2. 서브에이전트 활용 시작
"core-engine-agent를 사용해서 거래 엔진 구현해줘"
"security-specialist로 API 키 암호화 시스템 만들어줘"
```

---

## ❗ 주의사항

### 보안 관련
- **절대로** API 키나 비밀번호를 코드에 하드코딩하지 마세요
- **모든 민감 정보**는 GitHub Secrets에 저장하세요
- **.env 파일**은 .gitignore에 포함되어 있으니 안전합니다

### 개발 관련  
- **main 브랜치**에는 절대 직접 푸시하지 마세요
- **모든 변경사항**은 Pull Request를 통해 진행하세요
- **커밋 메시지**는 Conventional Commits 형식을 따라주세요

---

**이 체크리스트를 완료하면 전문적인 개발 환경이 완성됩니다!** ✅