@echo off
echo ========================================
echo YKP Dashboard Laragon Setup
echo ========================================

echo.
echo 1. 환경 파일 설정...
copy .env.laragon .env

echo.
echo 2. Composer 패키지 설치...
composer install --optimize-autoloader

echo.
echo 3. 애플리케이션 키 생성...
php artisan key:generate

echo.
echo 4. 데이터베이스 생성...
mysql -u root -e "CREATE DATABASE IF NOT EXISTS ykp_dashboard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo.
echo 5. 마이그레이션 실행...
php artisan migrate --force

echo.
echo 6. 시드 데이터 생성...
php artisan db:seed

echo.
echo 7. 코드 포맷팅...
vendor\bin\pint

echo.
echo 8. 권한 설정...
php artisan storage:link

echo.
echo ========================================
echo 설정 완료! 
echo ========================================
echo.
echo 다음 명령어로 개발 서버를 시작하세요:
echo composer run dev
echo.
echo 또는 브라우저에서 접속:
echo http://ykp-dashboard.test
echo.
pause