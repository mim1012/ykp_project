@echo off
chcp 65001 >nul
REM ========================================
REM 백업 상태 확인 스크립트
REM ========================================

echo ================================================
echo    YKP ERP 백업 상태 확인
echo ================================================
echo.

REM 1. 작업 스케줄러 상태 확인
echo [1] 자동 백업 스케줄 상태:
echo --------------------------------
schtasks /query /tn "YKP_ERP_Daily_Backup" 2>nul
if %errorlevel% neq 0 (
    echo [경고] 자동 백업이 설정되지 않았습니다.
    echo        setup_auto_backup.bat을 실행하여 설정하세요.
) else (
    echo [성공] 자동 백업 활성화됨
)

echo.
echo [2] 최근 백업 파일:
echo --------------------------------
dir /b /o-d "%~dp0daily_*.sql" 2>nul
if %errorlevel% neq 0 (
    echo    백업 파일이 없습니다.
)

echo.
echo [3] 백업 로그 (최근 10줄):
echo --------------------------------
if exist "%~dp0backup_log.txt" (
    powershell -Command "Get-Content '%~dp0backup_log.txt' -Tail 10"
) else (
    echo    로그 파일이 없습니다.
)

echo.
echo [4] 디스크 공간:
echo --------------------------------
for /f "tokens=3" %%a in ('dir /-c "%~dp0" ^| findstr /c:"사용 가능"') do set FREE_SPACE=%%a
echo 사용 가능한 공간: %FREE_SPACE% 바이트

echo.
echo [5] 백업 파일 통계:
echo --------------------------------
set /a FILE_COUNT=0
set /a TOTAL_SIZE=0

for /f %%a in ('dir /b "%~dp0*.sql" 2^>nul ^| find /c /v ""') do set FILE_COUNT=%%a
echo 총 백업 파일 수: %FILE_COUNT%개

echo.
echo ================================================
echo    다음 작업:
echo ================================================
echo 1. 즉시 백업 실행: auto_backup_daily.bat
echo 2. 자동 백업 설정: setup_auto_backup.bat
echo 3. 백업 복원: restore_supabase.bat
echo 4. 로그 확인: type backup_log.txt
echo ================================================
echo.

echo 아무 키나 누르면 종료됩니다...
pause >nul