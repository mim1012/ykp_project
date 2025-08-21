@echo off
echo Starting YKP Dashboard Server...
echo.
echo ========================================
echo   YKP ERP System - Laravel Server
echo ========================================
echo.
echo Server starting at: http://localhost:8000
echo Excel Input Page: http://localhost:8000/sales/excel-input
echo Admin Panel: http://localhost:8000/admin
echo.
C:\laragon\bin\php\php-8.3.13-Win32-vs16-x64\php.exe artisan serve --host=127.0.0.1 --port=8000
pause