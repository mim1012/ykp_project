@echo off
echo ========================================
echo React JSX 오류 완전 해결
echo ========================================

echo.
echo 1. Vite 캐시 완전 삭제...
rmdir /s /q node_modules\.vite 2>nul
rmdir /s /q .vite 2>nul

echo.
echo 2. 브라우저 캐시 클리어 안내...
echo 다음을 수행하세요:
echo - Chrome: Ctrl+Shift+Delete → 모든 항목 선택 → 삭제
echo - 또는 시크릿 모드로 테스트: Ctrl+Shift+N

echo.
echo 3. Vite 개발 서버 재시작...
npm run dev -- --force

echo.
echo ========================================
echo 해결 완료!
echo ========================================
echo.
echo 브라우저에서 확인:
echo http://localhost:5173 (또는 Vite가 표시하는 포트)
echo.
echo 만약 여전히 오류가 발생하면:
echo 1. 브라우저 시크릿 모드 사용
echo 2. 다른 브라우저에서 테스트
echo 3. 하드 리프레시: Ctrl+F5
echo.
pause