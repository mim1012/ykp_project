@echo off
title Setup Daily Backup
color 0E
cls

echo ================================================
echo    YKP ERP Daily Backup Setup
echo ================================================
echo.

set /p PROJECT_ID=Enter Supabase Project ID:

if "%PROJECT_ID%"=="" (
    echo.
    echo ERROR: Project ID required!
    pause
    exit
)

echo.
echo Select backup time:
echo.
echo [1] 2:00 AM
echo [2] 3:00 AM
echo [3] 4:00 AM
echo [4] 12:00 AM (Midnight)
echo.
set /p TIME_CHOICE=Select (1-4):

if "%TIME_CHOICE%"=="1" set BACKUP_TIME=02:00
if "%TIME_CHOICE%"=="2" set BACKUP_TIME=03:00
if "%TIME_CHOICE%"=="3" set BACKUP_TIME=04:00
if "%TIME_CHOICE%"=="4" set BACKUP_TIME=00:00

echo.
echo ================================
echo Settings Confirmation
echo ================================
echo Project ID: %PROJECT_ID%
echo Backup Time: %BACKUP_TIME%
echo ================================
echo.

echo @echo off > daily_backup_task.cmd
echo echo Backup started: %%date%% %%time%% >> daily_backup_task.cmd
echo supabase db dump --project-id %PROJECT_ID% --file "D:\Project\ykp-dashboard\backups\daily_%%date:~0,4%%%%date:~5,2%%%%date:~8,2%%.sql" >> daily_backup_task.cmd
echo echo Backup completed: %%date%% %%time%% >> daily_backup_task.cmd

echo Backup script created!
echo.

echo Adding to Windows Task Scheduler...
schtasks /create /tn "YKP_Daily_Backup" /tr "D:\Project\ykp-dashboard\backups\daily_backup_task.cmd" /sc daily /st %BACKUP_TIME% /f

if %errorlevel% equ 0 (
    echo.
    echo ========================================
    echo    SUCCESS: Daily backup configured!
    echo ========================================
    echo.
    echo Automatic backup will run daily at %BACKUP_TIME%
    echo.
) else (
    echo.
    echo FAILED: Could not add to Task Scheduler
    echo Please run as Administrator
    echo.
)

echo.
echo Test backup now? (Y/N)
set /p TEST=Select:

if /i "%TEST%"=="Y" (
    echo.
    echo Running test backup...
    call daily_backup_task.cmd
    echo.
    echo Checking backup files:
    dir /b *.sql | findstr /i daily
)

echo.
echo Setup complete!
echo.
pause