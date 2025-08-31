@echo off
echo ===== 현재 SQLite 데이터 백업 =====
echo.

echo 현재 데이터베이스 상태:
php artisan tinker --execute="echo '사용자: ' . App\Models\User::count() . '명' . PHP_EOL; echo '매장: ' . App\Models\Store::count() . '개' . PHP_EOL; echo '개통: ' . App\Models\Sale::count() . '건' . PHP_EOL;"

echo.
echo SQLite 파일 복사 중...
mkdir backup 2>nul
copy database\database.sqlite backup\database-backup-%date:~0,4%%date:~5,2%%date:~8,2%.sqlite

echo.
echo 백업 완료! backup 폴더를 확인하세요.
pause