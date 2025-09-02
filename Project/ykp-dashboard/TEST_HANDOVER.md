# YKP Dashboard 테스트 인수인계 가이드

## 📋 프로젝트 개요
- **저장소**: https://github.com/mim1012/ykp_project
- **현재 브랜치**: `feature/auth-rbac-system`
- **서버**: Laravel + React (http://127.0.0.1:8000)

## 🎯 핵심 기능별 브랜치
| 브랜치 | 기능 | 상태 |
|--------|------|------|
| `feature/connection-form-excel` | 개통표 입력 (캘린더+AgGrid) | ✅ 완성 |
| `feature/dashboard-analytics` | 통합 대시보드 (KPI+차트) | ✅ 완성 |
| `feature/auth-rbac-system` | 인증+권한 관리 | ✅ 완성 |

## 🧪 테스트 계정
```javascript
const accounts = {
  headquarters: { email: 'hq@ykp.com', password: '123456', role: '본사' },
  branch: { email: 'branch@ykp.com', password: '123456', role: '지사' }, 
  store: { email: 'store@ykp.com', password: '123456', role: '매장' }
};
```

## 🚀 테스트 시작 방법

### A. 수동 테스트
```bash
# 1. 서버 시작
php artisan serve

# 2. 브라우저에서 테스트
# URL: http://127.0.0.1:8000
# 각 계정으로 로그인 후 기능 테스트
```

### B. Playwright 자동화 테스트
```bash
# 1. Playwright 설치
npm install --save-dev @playwright/test
npx playwright install

# 2. 테스트 실행
npx playwright test                    # 전체 테스트
npx playwright test --headed           # 브라우저 UI 표시
npx playwright test ykp-auth-test      # 권한 테스트만
npx playwright test ykp-full-workflow  # 워크플로우 테스트만

# 3. 테스트 결과 보기
npx playwright show-report
```

## 📊 테스트해야 할 주요 시나리오

### 1️⃣ 권한별 로그인 테스트
- [ ] 본사 계정 로그인 → 대시보드 "🏢 본사 관리자" 표시
- [ ] 지사 계정 로그인 → 대시보드 "🏬 지사 관리자" 표시  
- [ ] 매장 계정 로그인 → 대시보드 "🏪 매장 직원" 표시
- [ ] 로그아웃 기능 정상 작동

### 2️⃣ 개통표 입력 테스트
- [ ] 사이드바 "완전한 판매관리" 클릭 → AgGrid 페이지 이동
- [ ] 📅 달력 버튼 → 미니 캘린더 표시
- [ ] 날짜 클릭 → 해당 날짜 개통표 로드
- [ ] 데이터 입력 → 저장 → 조회 확인

### 3️⃣ 권한별 데이터 접근 테스트
- [ ] 본사: 모든 매장 데이터 조회 가능
- [ ] 지사: 해당 지사 매장만 조회 가능
- [ ] 매장: 자기 매장 데이터만 조회 가능

### 4️⃣ 매장 관리 테스트 (본사만)
- [ ] 사이드바 "매장 관리" → 매장 목록 표시
- [ ] 매장 추가 기능
- [ ] 매장용 계정 생성 기능

## 🔧 문제 발생 시 해결 방법

### CSRF 토큰 오류 (419)
```bash
php artisan key:generate
php artisan config:clear
```

### 로그인 안 됨
```bash
# 테스트 계정 다시 생성
php artisan tinker --execute="App\Models\User::create(['name' => '본사 관리자', 'email' => 'hq@ykp.com', 'password' => bcrypt('123456'), 'role' => 'headquarters']);"
```

### React 사이드바 안 보임
```bash
npm run build
```

## 📱 접속 URL들
- **메인**: http://127.0.0.1:8000
- **대시보드**: http://127.0.0.1:8000/dashboard  
- **개통표**: http://127.0.0.1:8000/test/complete-aggrid
- **매장관리**: http://127.0.0.1:8000/management/stores

## 💡 다른 Claude에게 전달 시 말할 것
"YKP Dashboard 프로젝트 테스트를 도와주세요. GitHub에서 브랜치별 기능을 확인하고, 권한별 로그인 및 개통표 입력 기능을 테스트해주세요. 위 계정 정보와 테스트 시나리오를 참고해주세요."