@echo off
echo ================================
echo YKP Dashboard System Test Suite
echo ================================

echo.
echo [1/5] Database Health Check...
php artisan migrate:status
php artisan tinker --execute="echo 'DealerProfiles: ' . App\Models\DealerProfile::count();"

echo.
echo [2/5] API Endpoint Tests...
echo Testing basic calculation...
curl -X POST http://127.0.0.1:8000/api/calculation/row -H "Content-Type: application/json" -d "{\"price_setting\": 100000, \"verbal1\": 30000}" -s | findstr settlement

echo.
echo Testing batch calculation...
curl -X POST http://127.0.0.1:8000/api/calculation/batch -H "Content-Type: application/json" -d "{\"rows\": [{\"price_setting\": 100000, \"verbal1\": 20000}, {\"price_setting\": 150000, \"verbal1\": 30000}]}" -s | findstr success

echo.
echo [3/5] Frontend Build Test...
npm run build

echo.
echo [4/5] Route Verification...
php artisan route:list | findstr calculation

echo.
echo [5/5] AgGrid Page Test...
curl -I http://127.0.0.1:8000/sales/aggrid

echo.
echo ================================
echo Test Suite Complete!
echo ================================
pause