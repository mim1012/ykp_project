@echo off
title YKP ERP 자동 백업 설정
color 0A
cd /d D:\Project\ykp-dashboard\backups

echo ================================================
echo    YKP ERP 자동 백업 설정
echo ================================================
echo.
echo PowerShell 스크립트를 실행합니다...
echo.

REM PowerShell 실행 권한 설정 및 스크립트 실행
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "& {Start-Process PowerShell.exe -ArgumentList '-NoProfile -ExecutionPolicy Bypass -File ""D:\Project\ykp-dashboard\backups\setup_auto_backup.ps1""' -Verb RunAs}"

echo.
echo PowerShell 창이 열렸습니다.
echo 설정을 완료하신 후 이 창을 닫으셔도 됩니다.
echo.
pause