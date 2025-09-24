@echo off
title Backup Status
color 0A
cls

echo ================================================
echo    YKP ERP Backup Status Check
echo ================================================
echo.

echo [1] Scheduled Task Status:
echo --------------------------------
schtasks /query /tn "YKP_Daily_Backup" 2>nul
if %errorlevel% neq 0 (
    echo    No automatic backup configured
    echo    Run setup_daily_backup.cmd to configure
) else (
    echo    Automatic backup is active
)

echo.
echo [2] Recent Backup Files:
echo --------------------------------
dir /b /o-d *.sql 2>nul | findstr /v /c:"No files found"
if %errorlevel% neq 0 (
    echo    No backup files found
)

echo.
echo [3] Backup Folder Size:
echo --------------------------------
dir *.sql 2>nul | findstr /i "File(s)"

echo.
echo ================================================
echo    Available Commands:
echo ================================================
echo 1. backup_now.cmd         - Run backup now
echo 2. setup_daily_backup.cmd - Setup auto backup
echo 3. check_status.cmd       - This status check
echo ================================================
echo.
echo Press any key to exit...
pause >nul