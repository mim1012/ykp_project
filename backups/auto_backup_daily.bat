@echo off
chcp 65001 >nul
REM ========================================
REM YKP ERP 매일 자동 백업 스크립트
REM 실행 시간: 매일 새벽 2시 (권장)
REM ========================================

REM 여기에 실제 프로젝트 ID를 입력하세요!
set PROJECT_ID=YOUR_PROJECT_ID_HERE

REM 백업 폴더 경로
set BACKUP_DIR=D:\Project\ykp-dashboard\backups

REM 로그 파일 경로
set LOG_FILE=%BACKUP_DIR%\backup_log.txt

REM 날짜/시간 설정
for /f "tokens=2 delims==" %%a in ('wmic OS Get localdatetime /value') do set "dt=%%a"
set "YYYY=%dt:~0,4%" & set "MM=%dt:~4,2%" & set "DD=%dt:~6,2%"
set "HH=%dt:~8,2%" & set "Min=%dt:~10,2%" & set "Sec=%dt:~12,2%"
set "timestamp=%YYYY%%MM%%DD%_%HH%%Min%%Sec%"
set "date_only=%YYYY%-%MM%-%DD%"

echo ========================================== >> "%LOG_FILE%"
echo 백업 시작: %date_only% %HH%:%Min%:%Sec% >> "%LOG_FILE%"
echo ========================================== >> "%LOG_FILE%"

REM Supabase 로그인 체크
supabase projects list >nul 2>&1
if %errorlevel% neq 0 (
    echo [오류] Supabase 로그인이 필요합니다 >> "%LOG_FILE%"
    echo Supabase에 로그인되어 있지 않습니다. >> "%LOG_FILE%"
    echo 'supabase login' 명령을 실행해주세요. >> "%LOG_FILE%"
    exit /b 1
)

REM 1. 전체 데이터베이스 백업
echo [1/3] 전체 DB 백업 중... >> "%LOG_FILE%"
supabase db dump --project-id %PROJECT_ID% --file "%BACKUP_DIR%\daily_full_%timestamp%.sql" 2>> "%LOG_FILE%"
if %errorlevel% equ 0 (
    echo    [성공] 전체 백업 완료 >> "%LOG_FILE%"
) else (
    echo    [실패] 전체 백업 실패 >> "%LOG_FILE%"
)

REM 2. 중요 테이블만 별도 백업 (sales)
echo [2/3] 판매 데이터 백업 중... >> "%LOG_FILE%"
supabase db dump --project-id %PROJECT_ID% --table sales --file "%BACKUP_DIR%\daily_sales_%timestamp%.sql" 2>> "%LOG_FILE%"
if %errorlevel% equ 0 (
    echo    [성공] 판매 데이터 백업 완료 >> "%LOG_FILE%"
) else (
    echo    [실패] 판매 데이터 백업 실패 >> "%LOG_FILE%"
)

REM 3. 스키마 백업 (구조만)
echo [3/3] 스키마 백업 중... >> "%LOG_FILE%"
supabase db dump --project-id %PROJECT_ID% --schema-only --file "%BACKUP_DIR%\daily_schema_%timestamp%.sql" 2>> "%LOG_FILE%"
if %errorlevel% equ 0 (
    echo    [성공] 스키마 백업 완료 >> "%LOG_FILE%"
) else (
    echo    [실패] 스키마 백업 실패 >> "%LOG_FILE%"
)

REM 오래된 백업 파일 정리 (7일 이상)
echo. >> "%LOG_FILE%"
echo 7일 이상 된 백업 파일 정리 중... >> "%LOG_FILE%"
forfiles /p "%BACKUP_DIR%" /s /m daily_*.sql /d -7 /c "cmd /c del @path" 2>nul
if %errorlevel% equ 0 (
    echo    [성공] 오래된 파일 정리 완료 >> "%LOG_FILE%"
) else (
    echo    - 정리할 파일 없음 >> "%LOG_FILE%"
)

echo. >> "%LOG_FILE%"
echo 백업 완료: %date_only% %HH%:%Min%:%Sec% >> "%LOG_FILE%"
echo ========================================== >> "%LOG_FILE%"
echo. >> "%LOG_FILE%"

exit /b 0