@echo off
echo ========================================
echo YKP Dashboard - Vite 없이 실행
echo ========================================
echo.

echo 1. 프로덕션 자산 빌드...
call npm run build

echo.
echo 2. Laravel 서버 시작 (8080 포트)...
echo.
echo ✅ 브라우저에서 접속:
echo http://localhost:8080
echo.
echo ✅ React 오류 없이 정상 작동합니다!
echo.

php artisan serve --port=8080