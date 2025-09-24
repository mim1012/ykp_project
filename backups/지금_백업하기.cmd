@echo off
title 수동 백업
color 0B
cls

echo ================================================
echo    YKP ERP 수동 백업
echo ================================================
echo.

set /p PROJECT_ID=Project ID 입력 (예: abc123def456):

if "%PROJECT_ID%"=="" (
    echo.
    echo [오류] Project ID 필요!
    pause
    exit
)

echo.
echo 백업 시작...
echo.

REM 날짜 시간 변수 설정
set YEAR=%date:~0,4%
set MONTH=%date:~5,2%
set DAY=%date:~8,2%
set HOUR=%time:~0,2%
set MIN=%time:~3,2%
set SEC=%time:~6,2%
set TIMESTAMP=%YEAR%%MONTH%%DAY%_%HOUR%%MIN%%SEC%

REM 공백 제거
set TIMESTAMP=%TIMESTAMP: =0%

echo [1/3] 전체 백업...
supabase db dump --project-id %PROJECT_ID% --file "backup_full_%TIMESTAMP%.sql"

echo.
echo [2/3] 판매 데이터 백업...
supabase db dump --project-id %PROJECT_ID% --table sales --file "backup_sales_%TIMESTAMP%.sql"

echo.
echo [3/3] 스키마 백업...
supabase db dump --project-id %PROJECT_ID% --schema-only --file "backup_schema_%TIMESTAMP%.sql"

echo.
echo ================================================
echo    백업 완료!
echo ================================================
echo.
echo 생성된 파일:
dir /b backup_*%TIMESTAMP%*.sql
echo.
echo 백업 위치: %cd%
echo.
pause