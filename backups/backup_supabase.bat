@echo off
REM Supabase 백업 스크립트 (Windows)
REM 사용법: backup_supabase.bat YOUR_PROJECT_ID

set PROJECT_ID=%1
if "%PROJECT_ID%"=="" (
    echo 사용법: backup_supabase.bat PROJECT_ID
    echo 예시: backup_supabase.bat abc123def456
    exit /b 1
)

REM 백업 파일명에 날짜/시간 포함
for /f "tokens=2 delims==" %%a in ('wmic OS Get localdatetime /value') do set "dt=%%a"
set "YY=%dt:~2,2%" & set "YYYY=%dt:~0,4%" & set "MM=%dt:~4,2%" & set "DD=%dt:~6,2%"
set "HH=%dt:~8,2%" & set "Min=%dt:~10,2%" & set "Sec=%dt:~12,2%"
set "timestamp=%YYYY%%MM%%DD%_%HH%%Min%%Sec%"

echo ================================================
echo     YKP ERP 데이터베이스 백업 시작
echo ================================================
echo 프로젝트 ID: %PROJECT_ID%
echo 백업 시간: %timestamp%
echo.

REM 전체 백업 (스키마 + 데이터)
echo [1/4] 전체 데이터베이스 백업 중...
supabase db dump --project-id %PROJECT_ID% --file "backups\full_backup_%timestamp%.sql"
if %errorlevel% neq 0 (
    echo 오류: 전체 백업 실패
    pause
    exit /b 1
)

REM 스키마만 백업
echo [2/4] 스키마 백업 중...
supabase db dump --project-id %PROJECT_ID% --schema-only --file "backups\schema_backup_%timestamp%.sql"

REM 주요 테이블별 백업
echo [3/4] 판매 데이터 백업 중...
supabase db dump --project-id %PROJECT_ID% --file "backups\sales_backup_%timestamp%.sql" --table sales

echo [4/4] 사용자 데이터 백업 중...
supabase db dump --project-id %PROJECT_ID% --file "backups\users_backup_%timestamp%.sql" --table users

echo.
echo ================================================
echo     백업 완료!
echo ================================================
echo 백업 파일들:
dir /b backups\*%timestamp%*
echo.
echo 백업 폴더 위치: %cd%\backups
pause