@echo off
echo ========================================
echo YKP Dashboard - Laravel만 실행 (8080)
echo ========================================
echo.

echo Vite 없이 Laravel만 실행합니다...
echo 이전에 작동했던 방식과 동일합니다.
echo.

echo 자산 빌드 중...
npm run build

echo.
echo Laravel 서버 시작 (8080 포트)...
echo 브라우저에서 http://localhost:8080 으로 접속하세요
echo.

php artisan serve --port=8080