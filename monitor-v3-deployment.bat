@echo off
setlocal enabledelayedexpansion

echo ========================================
echo v3 Deployment Monitor
echo ========================================
echo Start Time: %date% %time%
echo.

set "START_TIME=%time%"
set "MAX_ITERATIONS=20"
set "ITERATION=0"

:MONITOR_LOOP
set /a ITERATION+=1
echo.
echo [%ITERATION%/%MAX_ITERATIONS%] Checking health status at %time%...

REM Check health endpoint
curl -s https://ykperp.co.kr/health.php > temp_health.txt
set /p HEALTH_STATUS=<temp_health.txt

echo Response: %HEALTH_STATUS%

REM Check if v3 is detected
echo %HEALTH_STATUS% | findstr /C:"v3" >nul
if %errorlevel% equ 0 (
    echo.
    echo ========================================
    echo ✅ v3 DETECTED! Deployment Complete!
    echo ========================================
    echo Deployment completed at: %date% %time%
    echo.
    goto RUN_TESTS
)

REM Check if max iterations reached
if %ITERATION% geq %MAX_ITERATIONS% (
    echo.
    echo ========================================
    echo ⚠️ TIMEOUT: v3 not detected after %MAX_ITERATIONS% attempts
    echo ========================================
    echo Last status: %HEALTH_STATUS%
    goto END
)

echo Waiting 30 seconds before next check...
timeout /t 30 /nobreak > nul
goto MONITOR_LOOP

:RUN_TESTS
echo.
echo ========================================
echo Running API Tests...
echo ========================================
echo.

echo === Test 1: Basic Store Listing (per_page=20) ===
curl -s "https://ykperp.co.kr/api/stores?per_page=20" > test1_result.txt
type test1_result.txt
echo.
echo.

echo === Test 2: Search for "천호" ===
curl -s "https://ykperp.co.kr/api/stores?per_page=10&search=천호" > test2_result.txt
type test2_result.txt
echo.
echo.

echo === Test 3: Search for "부산" ===
curl -s "https://ykperp.co.kr/api/stores?per_page=10&search=부산" > test3_result.txt
type test3_result.txt
echo.
echo.

echo ========================================
echo Test Results Analysis
echo ========================================

REM Check Test 1
findstr /C:"success" test1_result.txt >nul
if %errorlevel% equ 0 (
    echo ✅ Test 1: Response contains "success" field
) else (
    echo ❌ Test 1: No "success" field found ^(may require auth^)
)

REM Check Test 2
findstr /C:"success" test2_result.txt >nul
if %errorlevel% equ 0 (
    echo ✅ Test 2: Response contains "success" field
) else (
    echo ❌ Test 2: No "success" field found ^(may require auth^)
)

REM Check Test 3
findstr /C:"success" test3_result.txt >nul
if %errorlevel% equ 0 (
    echo ✅ Test 3: Response contains "success" field
) else (
    echo ❌ Test 3: No "success" field found ^(may require auth^)
)

echo.
echo ⚠️ NOTE: If all tests show authentication errors,
echo    this is expected as the API requires login.
echo    The v3 deployment itself is successful.
echo.

:END
echo ========================================
echo Monitoring Complete
echo ========================================
echo End Time: %date% %time%
echo.

REM Cleanup
if exist temp_health.txt del temp_health.txt

pause
