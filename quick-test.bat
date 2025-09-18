@echo off
echo ========================================
echo YKP Dashboard 빠른 테스트 스크립트
echo ========================================
echo.

echo 1. Laravel 서버 시작 중...
start "Laravel Server" cmd /k "php artisan serve"
timeout /t 3 /nobreak >nul

echo 2. 브라우저에서 테스트 페이지 열기...
start "YKP Dashboard" http://127.0.0.1:8000

echo.
echo 테스트 계정:
echo - 본사: hq@ykp.com / 123456
echo - 지사: branch@ykp.com / 123456  
echo - 매장: store@ykp.com / 123456
echo.

echo 3. Playwright 자동화 테스트 실행하려면 y를 누르세요...
set /p choice=자동 테스트 실행 (y/n)? 
if /i "%choice%"=="y" (
    echo Playwright 테스트 실행 중...
    npx playwright test --headed
    npx playwright show-report
) else (
    echo 수동 테스트를 진행해주세요.
)

echo.
echo 테스트 완료! 서버는 계속 실행됩니다.
pause