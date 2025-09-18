@echo off
echo ===== YKP ERP Supabase 마이그레이션 =====
echo.

echo 1. 현재 데이터 백업 중...
php artisan backup:database backup/sqlite-backup.sql

echo 2. Supabase 연결 테스트...
php artisan migrate:status

echo 3. 마이그레이션 실행...
php artisan migrate:fresh

echo 4. 기본 데이터 시딩...
php artisan db:seed --class=HeadquartersUserSeeder
php artisan db:seed --class=DealerProfileSeeder  
php artisan db:seed --class=AdvancedSeeder

echo 5. 캐시 클리어...
php artisan config:clear
php artisan cache:clear

echo.
echo ===== 마이그레이션 완료! =====
echo Supabase 대시보드에서 데이터를 확인하세요.
pause