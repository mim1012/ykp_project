@echo off
echo ===================================
echo YKP Dashboard Test Suite
echo ===================================
echo.

echo [1/4] Code Formatting Check...
call .\vendor\bin\pint --test
if %errorlevel% neq 0 (
    echo Code formatting issues found! Run: .\vendor\bin\pint
    exit /b 1
)
echo ✓ Code formatting OK
echo.

echo [2/4] Static Analysis...
call .\vendor\bin\phpstan analyse --level=5
if %errorlevel% neq 0 (
    echo Static analysis issues found!
    exit /b 1
)
echo ✓ Static analysis OK
echo.

echo [3/4] Unit Tests...
call .\vendor\bin\phpunit tests\Unit --no-coverage
if %errorlevel% neq 0 (
    echo Unit tests failed!
    exit /b 1
)
echo ✓ Unit tests OK
echo.

echo [4/4] E2E Smoke Tests...
call npm run test:smoke
if %errorlevel% neq 0 (
    echo E2E tests failed!
    exit /b 1
)
echo ✓ E2E tests OK
echo.

echo ===================================
echo All tests passed successfully!
echo ===================================