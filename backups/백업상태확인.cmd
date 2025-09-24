@echo off
cd /d D:\Project\ykp-dashboard\backups
echo ================================================
echo    YKP ERP 백업 상태 확인
echo ================================================
echo.
echo [1] 자동 백업 스케줄:
schtasks /query /tn "YKP_ERP_Daily_Backup" 2>nul
if %errorlevel% neq 0 (
    echo    자동 백업이 설정되지 않았습니다.
    echo    실행_자동백업설정.cmd 를 실행하세요.
) else (
    echo    자동 백업 활성화됨
)
echo.
echo [2] 최근 백업 파일:
dir /b /o-d daily_*.sql 2>nul
echo.
echo [3] 백업 폴더 열기를 원하면 E 입력
echo [4] 종료하려면 아무 키나 누르세요
set /p choice=선택:
if /i "%choice%"=="E" (
    explorer D:\Project\ykp-dashboard\backups
)
pause