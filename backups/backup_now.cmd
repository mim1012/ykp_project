@echo off
title Manual Backup
color 0B
cls

echo ================================================
echo    YKP ERP Manual Backup
echo ================================================
echo.

set /p PROJECT_ID=Enter Project ID:

if "%PROJECT_ID%"=="" (
    echo.
    echo ERROR: Project ID required!
    pause
    exit
)

echo.
echo Starting backup...
echo.

set YEAR=%date:~0,4%
set MONTH=%date:~5,2%
set DAY=%date:~8,2%
set HOUR=%time:~0,2%
set MIN=%time:~3,2%
set TIMESTAMP=%YEAR%%MONTH%%DAY%_%HOUR%%MIN%

set TIMESTAMP=%TIMESTAMP: =0%

echo [1/3] Full backup...
supabase db dump --project-id %PROJECT_ID% --file "backup_full_%TIMESTAMP%.sql"

echo.
echo [2/3] Sales table backup...
supabase db dump --project-id %PROJECT_ID% --table sales --file "backup_sales_%TIMESTAMP%.sql"

echo.
echo [3/3] Schema backup...
supabase db dump --project-id %PROJECT_ID% --schema-only --file "backup_schema_%TIMESTAMP%.sql"

echo.
echo ================================================
echo    Backup Complete!
echo ================================================
echo.
echo Created files:
dir /b backup_*%TIMESTAMP%*.sql
echo.
echo Location: %cd%
echo.
pause