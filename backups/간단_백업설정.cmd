@echo off
title YKP ERP 백업 설정
color 0E
cls

echo ================================================
echo    YKP ERP 매일 자동 백업 설정
echo ================================================
echo.

set /p PROJECT_ID=Supabase Project ID 입력:

if "%PROJECT_ID%"=="" (
    echo.
    echo [오류] Project ID를 입력해야 합니다!
    pause
    exit
)

echo.
echo 백업 시간을 선택하세요:
echo.
echo [1] 새벽 2시
echo [2] 새벽 3시
echo [3] 새벽 4시
echo [4] 자정 (00:00)
echo.
set /p TIME_CHOICE=선택 (1-4):

if "%TIME_CHOICE%"=="1" set BACKUP_TIME=02:00
if "%TIME_CHOICE%"=="2" set BACKUP_TIME=03:00
if "%TIME_CHOICE%"=="3" set BACKUP_TIME=04:00
if "%TIME_CHOICE%"=="4" set BACKUP_TIME=00:00

echo.
echo ================================
echo 설정 확인
echo ================================
echo Project ID: %PROJECT_ID%
echo 백업 시간: %BACKUP_TIME%
echo ================================
echo.

REM 백업 스크립트 생성
echo @echo off > daily_backup.cmd
echo echo 백업 시작: %%date%% %%time%% >> daily_backup.cmd
echo supabase db dump --project-id %PROJECT_ID% --file "D:\Project\ykp-dashboard\backups\backup_%%date:~0,4%%%%date:~5,2%%%%date:~8,2%%.sql" >> daily_backup.cmd
echo echo 백업 완료: %%date%% %%time%% >> daily_backup.cmd

echo 백업 스크립트 생성 완료!
echo.

echo Windows 작업 스케줄러에 등록 중...
schtasks /create /tn "YKP_Daily_Backup" /tr "D:\Project\ykp-dashboard\backups\daily_backup.cmd" /sc daily /st %BACKUP_TIME% /f

if %errorlevel% equ 0 (
    echo.
    echo ========================================
    echo    [성공] 자동 백업 설정 완료!
    echo ========================================
    echo.
    echo 매일 %BACKUP_TIME%에 자동 백업됩니다.
    echo.
) else (
    echo.
    echo [실패] 작업 스케줄러 등록 실패
    echo 관리자 권한으로 다시 실행해주세요.
    echo.
)

echo.
echo 테스트로 지금 백업하시겠습니까? (Y/N)
set /p TEST=선택:

if /i "%TEST%"=="Y" (
    echo.
    echo 테스트 백업 실행 중...
    call daily_backup.cmd
    echo.
    echo 백업 파일 확인:
    dir /b *.sql | findstr /i backup
)

echo.
echo 설정이 완료되었습니다!
echo.
pause