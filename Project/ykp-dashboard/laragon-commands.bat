@echo off
echo ========================================
echo YKP Dashboard 개발 명령어 모음
echo ========================================
echo.

:menu
echo 실행할 작업을 선택하세요:
echo.
echo 1. 개발 서버 시작 (Laravel + Vite + Queue)
echo 2. 테스트 실행
echo 3. 코드 품질 검사
echo 4. 데이터베이스 초기화
echo 5. 성능 분석
echo 6. 프로덕션 빌드
echo 7. 종료
echo.

set /p choice="선택 (1-7): "

if "%choice%"=="1" goto dev_server
if "%choice%"=="2" goto run_tests
if "%choice%"=="3" goto quality_check
if "%choice%"=="4" goto db_reset
if "%choice%"=="5" goto performance
if "%choice%"=="6" goto build
if "%choice%"=="7" goto end

echo 잘못된 선택입니다.
goto menu

:dev_server
echo.
echo 🚀 개발 서버 시작 중...
composer run dev
goto menu

:run_tests
echo.
echo 🧪 테스트 실행 중...
php artisan test
echo.
echo 단위 테스트만:
php artisan test tests/Unit/SalesCalculatorTest.php
goto menu

:quality_check
echo.
echo 🔍 코드 품질 검사...
echo.
echo --- 코드 포맷팅 검사 ---
vendor\bin\pint --test
echo.
echo --- 정적 분석 (메모리 512MB로 실행) ---
php -d memory_limit=512M vendor\bin\phpstan analyse
echo.
echo --- 모든 품질 검사 ---
composer run quality
goto menu

:db_reset
echo.
echo 🗄️ 데이터베이스 초기화...
php artisan migrate:fresh --seed
echo 완료!
goto menu

:performance
echo.
echo ⚡ 성능 분석...
echo.
echo --- 라우트 캐시 ---
php artisan route:cache
echo.
echo --- 설정 캐시 ---
php artisan config:cache
echo.
echo --- 뷰 캐시 ---
php artisan view:cache
echo.
echo 성능 최적화 완료!
goto menu

:build
echo.
echo 🏗️ 프로덕션 빌드...
npm run build
php artisan optimize
echo 빌드 완료!
goto menu

:end
echo.
echo 👋 개발 작업을 마칩니다!
exit