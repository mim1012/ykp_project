@echo off
REM Supabase 복원 스크립트 (Windows)
REM 사용법: restore_supabase.bat PROJECT_ID BACKUP_FILE

set PROJECT_ID=%1
set BACKUP_FILE=%2

if "%PROJECT_ID%"=="" (
    echo 사용법: restore_supabase.bat PROJECT_ID BACKUP_FILE
    echo 예시: restore_supabase.bat abc123def456 backups\full_backup_20250923_051500.sql
    echo.
    echo 사용 가능한 백업 파일들:
    dir /b backups\*.sql
    exit /b 1
)

if "%BACKUP_FILE%"=="" (
    echo 백업 파일을 지정해주세요.
    echo 사용 가능한 백업 파일들:
    dir /b backups\*.sql
    exit /b 1
)

if not exist "%BACKUP_FILE%" (
    echo 오류: 백업 파일 '%BACKUP_FILE%'을 찾을 수 없습니다.
    exit /b 1
)

echo ================================================
echo     YKP ERP 데이터베이스 복원 시작
echo ================================================
echo 프로젝트 ID: %PROJECT_ID%
echo 백업 파일: %BACKUP_FILE%
echo.

echo 경고: 이 작업은 기존 데이터를 덮어씁니다!
set /p confirm=계속하시겠습니까? (y/N):
if /i not "%confirm%"=="y" (
    echo 복원이 취소되었습니다.
    pause
    exit /b 0
)

echo.
echo 복원 중... 잠시만 기다려주세요.

REM PostgreSQL 클라이언트를 통한 복원
REM Supabase CLI에서 직접 복원 명령어가 없으므로 psql 사용
echo 수동으로 Supabase 대시보드 > SQL Editor에서 다음 파일을 실행하세요:
echo 파일: %BACKUP_FILE%
echo.
echo 또는 PostgreSQL 클라이언트를 사용하여:
echo psql "postgresql://postgres:[password]@[host]:5432/postgres" -f "%BACKUP_FILE%"

pause