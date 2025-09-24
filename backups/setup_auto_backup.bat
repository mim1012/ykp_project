@echo off
chcp 65001 >nul
REM ============================================
REM Windows 작업 스케줄러 자동 백업 설정 스크립트
REM ============================================

echo ================================================
echo    YKP ERP 매일 자동 백업 설정 마법사
echo ================================================
echo.

REM 관리자 권한 확인
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo [경고] 이 스크립트는 관리자 권한이 필요합니다.
    echo        마우스 오른쪽 버튼 - 관리자 권한으로 실행
    pause
    exit /b 1
)

REM 프로젝트 ID 입력
set /p PROJECT_ID=Supabase Project ID 입력:
if "%PROJECT_ID%"=="" (
    echo [오류] 프로젝트 ID가 필요합니다.
    pause
    exit /b 1
)

REM auto_backup_daily.bat 파일 업데이트
echo 백업 스크립트 설정 중...
powershell -Command "(Get-Content 'D:\Project\ykp-dashboard\backups\auto_backup_daily.bat') -replace 'YOUR_PROJECT_ID_HERE', '%PROJECT_ID%' | Set-Content 'D:\Project\ykp-dashboard\backups\auto_backup_daily.bat'"

REM 백업 시간 선택
echo.
echo 백업 시간을 선택하세요:
echo [1] 새벽 2시 (권장 - 서버 부하 최소)
echo [2] 새벽 3시
echo [3] 새벽 4시
echo [4] 자정 (00:00)
echo [5] 사용자 지정
set /p TIME_CHOICE=선택 (1-5):

if "%TIME_CHOICE%"=="1" set BACKUP_TIME=02:00
if "%TIME_CHOICE%"=="2" set BACKUP_TIME=03:00
if "%TIME_CHOICE%"=="3" set BACKUP_TIME=04:00
if "%TIME_CHOICE%"=="4" set BACKUP_TIME=00:00
if "%TIME_CHOICE%"=="5" (
    set /p BACKUP_TIME=시간 입력 (예: 14:30):
)

echo.
echo 선택한 백업 시간: %BACKUP_TIME%
echo.

REM 작업 스케줄러에 작업 생성
echo Windows 작업 스케줄러에 등록 중...

REM 기존 작업이 있으면 삭제
schtasks /delete /tn "YKP_ERP_Daily_Backup" /f >nul 2>&1

REM 새 작업 생성
schtasks /create ^
    /tn "YKP_ERP_Daily_Backup" ^
    /tr "D:\Project\ykp-dashboard\backups\auto_backup_daily.bat" ^
    /sc daily ^
    /st %BACKUP_TIME% ^
    /ru "%USERNAME%" ^
    /rl highest ^
    /f

if %errorlevel% equ 0 (
    echo.
    echo [성공] 자동 백업 설정 완료!
    echo.
    echo ================================================
    echo    설정 정보
    echo ================================================
    echo 백업 주기: 매일
    echo 백업 시간: %BACKUP_TIME%
    echo 백업 위치: D:\Project\ykp-dashboard\backups\
    echo 프로젝트 ID: %PROJECT_ID%
    echo 로그 파일: backup_log.txt
    echo ================================================
    echo.
    echo 다음 명령으로 작업 상태를 확인할 수 있습니다:
    echo    schtasks /query /tn "YKP_ERP_Daily_Backup" /v
    echo.
    echo 작업 수정이 필요하면:
    echo    작업 스케줄러 (taskschd.msc) 실행 후
    echo    "YKP_ERP_Daily_Backup" 작업 편집
    echo.
) else (
    echo.
    echo [오류] 작업 생성 실패
    echo       수동으로 작업 스케줄러를 열어 설정해주세요.
    echo       작업 스케줄러 실행: Win+R - taskschd.msc
)

echo.
echo 즉시 테스트 백업을 실행하시겠습니까? (Y/N)
set /p TEST_NOW=선택:
if /i "%TEST_NOW%"=="Y" (
    echo.
    echo 테스트 백업 실행 중...
    call "D:\Project\ykp-dashboard\backups\auto_backup_daily.bat"
    echo.
    echo 백업 로그 확인:
    type "D:\Project\ykp-dashboard\backups\backup_log.txt" 2>nul | findstr /i "백업"
)

echo.
echo 아무 키나 누르면 종료됩니다...
pause >nul