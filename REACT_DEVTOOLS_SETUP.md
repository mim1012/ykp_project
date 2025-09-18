# 🛠️ React DevTools 설정 가이드

## ⚡ 빠른 해결법 (Laragon에서)

### **1. 브라우저 확장 설치**
```
Chrome: https://chrome.google.com/webstore/detail/fmkadmapgofadopljbjfkapdkoienihi
Firefox: https://addons.mozilla.org/en-US/firefox/addon/react-devtools/
Edge: https://microsoftedge.microsoft.com/addons/detail/gpphkfbcpidddadnkolkpfckpihlkkil
```

### **2. Laragon 터미널에서 실행**
```bash
# 프로젝트 이동
cd C:\laragon\www\ykp-dashboard

# Vite 개발 서버 시작
npm run dev

# 또는 전체 개발 환경
composer run dev
```

### **3. 브라우저에서 확인**
```
http://ykp-dashboard.test
```

---

## 🔧 **Vite 오류 해결 완료 사항**

### ✅ **해결된 문제들**
1. **JSX 프리앰블 오류**: `React.createElement` → 정식 JSX 문법으로 변경
2. **@vitejs/plugin-react 감지 오류**: include 패턴 및 fastRefresh 설정 추가
3. **React DevTools 연동**: 개발 모드 최적화 설정

### ✅ **변경된 파일들**
- `resources/js/components/ui/Card.jsx` - JSX 문법으로 리팩토링
- `vite.config.js` - React 플러그인 설정 개선

---

## 🚀 **Laragon에서 실행할 명령어**

### **즉시 실행 (문제 해결)**
```bash
# Laragon Terminal 열기
cd C:\laragon\www\ykp-dashboard

# Node 모듈 재설치 (권장)
npm install

# Vite 개발 서버 재시작  
npm run dev

# 또는 브라우저 캐시 클리어 후
Ctrl + F5 (강제 새로고침)
```

### **개발 환경 전체 시작**
```bash
# 올인원 개발 서버 (Laravel + Vite + Queue)
composer run dev

# 또는 개별 실행:
php artisan serve          # Laravel 백엔드
npm run dev                # React 프론트엔드
php artisan queue:work     # 큐 처리
```

---

## 🐛 **문제 해결 체크리스트**

### **React DevTools 연결 안 될 때**
```bash
# 1. 브라우저 개발자 도구 열기 (F12)
# 2. Console 탭에서 에러 확인
# 3. React 탭이 보이는지 확인

# 만약 React 탭이 없다면:
# - 브라우저 확장 프로그램 재설치
# - 브라우저 캐시 완전 삭제
# - 시크릿/인코그니토 모드에서 테스트
```

### **Vite HMR (Hot Module Replacement) 안될 때**
```bash
# Vite 캐시 클리어
npm run dev -- --force

# 또는 node_modules 재설치
rm -rf node_modules package-lock.json
npm install
npm run dev
```

### **JSX 에러 계속 발생시**
```bash
# TypeScript 체크 비활성화 (임시)
# vite.config.js에서:
esbuild: {
    loader: 'jsx',
    include: /.*\.jsx?$/,
    exclude: []
}
```

---

## 📊 **성능 최적화된 개발 명령어들**

### **🔥 빠른 개발 모드**
```bash
# HMR 최적화
npm run dev -- --host 0.0.0.0 --port 5173

# 빌드 시간 단축
npm run dev -- --no-clearScreen
```

### **🧪 테스트 + 개발 동시**
```bash
# 백그라운드로 테스트 워치
php artisan test --watch &

# 프론트엔드 개발 서버
npm run dev
```

### **📈 성능 모니터링**
```bash
# 번들 크기 분석
npm run analyze

# Laravel 디버그바 (설치 시)
composer require barryvdh/laravel-debugbar --dev
```

---

## 🎯 **Laragon 최적화 팁**

### **가상 호스트 자동 설정**
```bash
# 프로젝트명.test 자동 생성
# ykp-dashboard.test → 자동 접근 가능

# SSL 자동 설정 (Laragon Pro)
# https://ykp-dashboard.test
```

### **데이터베이스 연결 최적화**
```bash
# .env 파일에서 MySQL 설정:
DB_CONNECTION=mysql
DB_HOST=127.0.0.1  
DB_PORT=3306
DB_DATABASE=ykp_dashboard
DB_USERNAME=root
DB_PASSWORD=        # Laragon은 기본적으로 비밀번호 없음
```

### **성능 최적화 설정**
```bash
# 캐시 설정
php artisan config:cache
php artisan route:cache
php artisan view:cache

# OPcache 활성화 (Laragon에서 기본 제공)
# 자동으로 성능 향상됨
```

---

## 🎊 **최종 실행 가이드 (Laragon)**

### **🚀 한 번에 모든 설정**
```bash
# 1. Laragon 시작
# 2. Terminal 열기  
# 3. 다음 명령어 실행:

cd C:\laragon\www\ykp-dashboard
copy .env.laragon .env
composer install
npm install  
php artisan key:generate
php artisan migrate --seed
npm run dev
```

### **📱 브라우저에서 확인**
```
http://ykp-dashboard.test        # 메인 사이트
http://ykp-dashboard.test/admin  # Filament 관리자
```

### **🛠️ 개발 도구 확인**
- **F12** → **React** 탭 (DevTools)
- **F12** → **Network** 탭 (HMR 확인)
- **F12** → **Console** (에러 확인)

---

## 🎯 **문제 해결됨 확인 방법**

### **✅ 성공 시 보이는 것들**
1. **브라우저 Console**: `Download the React DevTools...` 메시지 사라짐
2. **React DevTools**: F12 → React 탭 활성화
3. **Vite HMR**: 코드 변경 시 즉시 반영
4. **API 연결**: 통계 데이터 정상 로드

### **🎊 최종 체크리스트**
- [ ] Card.jsx 오류 해결 (JSX 문법 적용)
- [ ] React DevTools 브라우저 확장 설치
- [ ] Vite 개발 서버 정상 시작
- [ ] HMR (Hot Module Replacement) 작동
- [ ] API 엔드포인트 정상 응답

**Laragon에서는 이 모든 과정이 5분 내에 완료됩니다! 🚀**

<function_calls>
<invoke name="TodoWrite">
<parameter name="todos">[{"id": "1", "content": "Vite React \ud50c\ub7ec\uadf8\uc778 \uc124\uc815 \uc624\ub958 \ud574\uacb0", "status": "completed"}, {"id": "2", "content": "Card.jsx \ud30c\uc77c \ubb38\uc81c \ud30c\uc545 \ubc0f \uc218\uc815", "status": "completed"}, {"id": "3", "content": "React DevTools \uc124\uc815 \uac00\uc774\ub4dc \uc81c\uacf5", "status": "completed"}, {"id": "4", "content": "Vite \uc124\uc815 \ucd5c\uc801\ud654", "status": "completed"}]