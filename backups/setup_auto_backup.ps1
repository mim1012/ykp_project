# YKP ERP 자동 백업 설정 스크립트
# PowerShell 버전

Write-Host "================================================" -ForegroundColor Cyan
Write-Host "   YKP ERP 매일 자동 백업 설정 마법사" -ForegroundColor Yellow
Write-Host "================================================" -ForegroundColor Cyan
Write-Host ""

# 관리자 권한 확인
$currentPrincipal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
$isAdmin = $currentPrincipal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    Write-Host "[경고] 이 스크립트는 관리자 권한이 필요합니다." -ForegroundColor Red
    Write-Host "PowerShell을 관리자 권한으로 다시 실행해주세요." -ForegroundColor Red
    Write-Host "종료하려면 아무 키나 누르세요..."
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    exit
}

# Supabase 프로젝트 ID 입력
Write-Host "Supabase 프로젝트 ID를 입력하세요: " -NoNewline -ForegroundColor Green
$projectId = Read-Host
if ([string]::IsNullOrEmpty($projectId)) {
    Write-Host "[오류] 프로젝트 ID가 필요합니다." -ForegroundColor Red
    Write-Host "종료하려면 아무 키나 누르세요..."
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    exit
}

# auto_backup_daily.ps1 파일 생성/업데이트
$backupScriptPath = "D:\Project\ykp-dashboard\backups\auto_backup_daily.ps1"
$backupScriptContent = @"
# YKP ERP 일일 자동 백업 스크립트
`$projectId = "$projectId"
`$backupDir = "D:\Project\ykp-dashboard\backups"
`$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
`$logFile = "`$backupDir\backup_log.txt"

# 로그 시작
Add-Content `$logFile "=========================================="
Add-Content `$logFile "백업 시작: `$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
Add-Content `$logFile "=========================================="

# 전체 백업
Write-Host "[1/3] 전체 DB 백업 중..." -ForegroundColor Yellow
`$fullBackup = supabase db dump --project-id `$projectId --file "`$backupDir\daily_full_`$timestamp.sql" 2>&1
if (`$LASTEXITCODE -eq 0) {
    Add-Content `$logFile "   [성공] 전체 백업 완료"
    Write-Host "   [성공] 전체 백업 완료" -ForegroundColor Green
} else {
    Add-Content `$logFile "   [실패] 전체 백업 실패"
    Write-Host "   [실패] 전체 백업 실패" -ForegroundColor Red
}

# 판매 데이터 백업
Write-Host "[2/3] 판매 데이터 백업 중..." -ForegroundColor Yellow
`$salesBackup = supabase db dump --project-id `$projectId --table sales --file "`$backupDir\daily_sales_`$timestamp.sql" 2>&1
if (`$LASTEXITCODE -eq 0) {
    Add-Content `$logFile "   [성공] 판매 데이터 백업 완료"
    Write-Host "   [성공] 판매 데이터 백업 완료" -ForegroundColor Green
} else {
    Add-Content `$logFile "   [실패] 판매 데이터 백업 실패"
    Write-Host "   [실패] 판매 데이터 백업 실패" -ForegroundColor Red
}

# 스키마 백업
Write-Host "[3/3] 스키마 백업 중..." -ForegroundColor Yellow
`$schemaBackup = supabase db dump --project-id `$projectId --schema-only --file "`$backupDir\daily_schema_`$timestamp.sql" 2>&1
if (`$LASTEXITCODE -eq 0) {
    Add-Content `$logFile "   [성공] 스키마 백업 완료"
    Write-Host "   [성공] 스키마 백업 완료" -ForegroundColor Green
} else {
    Add-Content `$logFile "   [실패] 스키마 백업 실패"
    Write-Host "   [실패] 스키마 백업 실패" -ForegroundColor Red
}

# 7일 이상된 파일 삭제
Get-ChildItem "`$backupDir\daily_*.sql" | Where-Object { `$_.LastWriteTime -lt (Get-Date).AddDays(-7) } | Remove-Item -Force

Add-Content `$logFile ""
Add-Content `$logFile "백업 완료: `$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
Add-Content `$logFile "=========================================="
Add-Content `$logFile ""
"@

Set-Content -Path $backupScriptPath -Value $backupScriptContent -Encoding UTF8

Write-Host ""
Write-Host "백업 시간을 선택하세요:" -ForegroundColor Yellow
Write-Host "[1] 새벽 2시 (권장)" -ForegroundColor White
Write-Host "[2] 새벽 3시" -ForegroundColor White
Write-Host "[3] 새벽 4시" -ForegroundColor White
Write-Host "[4] 자정 (00:00)" -ForegroundColor White
Write-Host "[5] 사용자 지정" -ForegroundColor White
Write-Host "선택 (1-5): " -NoNewline -ForegroundColor Green
$timeChoice = Read-Host

switch ($timeChoice) {
    "1" { $backupTime = "02:00" }
    "2" { $backupTime = "03:00" }
    "3" { $backupTime = "04:00" }
    "4" { $backupTime = "00:00" }
    "5" {
        Write-Host "시간 입력 (예: 14:30): " -NoNewline -ForegroundColor Green
        $backupTime = Read-Host
    }
    default { $backupTime = "02:00" }
}

Write-Host ""
Write-Host "선택한 백업 시간: $backupTime" -ForegroundColor Cyan
Write-Host ""

# Windows 작업 스케줄러에 등록
Write-Host "Windows 작업 스케줄러에 등록 중..." -ForegroundColor Yellow

# 기존 작업 삭제
schtasks /delete /tn "YKP_ERP_Daily_Backup" /f 2>$null

# 새 작업 생성
$action = New-ScheduledTaskAction -Execute "PowerShell.exe" -Argument "-ExecutionPolicy Bypass -File `"$backupScriptPath`""
$trigger = New-ScheduledTaskTrigger -Daily -At $backupTime
$principal = New-ScheduledTaskPrincipal -UserId "$env:USERDOMAIN\$env:USERNAME" -LogonType S4U -RunLevel Highest
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable

try {
    Register-ScheduledTask -TaskName "YKP_ERP_Daily_Backup" -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Force

    Write-Host ""
    Write-Host "[성공] 자동 백업 설정 완료!" -ForegroundColor Green
    Write-Host ""
    Write-Host "================================================" -ForegroundColor Cyan
    Write-Host "   설정 정보" -ForegroundColor Yellow
    Write-Host "================================================" -ForegroundColor Cyan
    Write-Host "백업 주기: 매일" -ForegroundColor White
    Write-Host "백업 시간: $backupTime" -ForegroundColor White
    Write-Host "백업 위치: D:\Project\ykp-dashboard\backups\" -ForegroundColor White
    Write-Host "프로젝트 ID: $projectId" -ForegroundColor White
    Write-Host "로그 파일: backup_log.txt" -ForegroundColor White
    Write-Host "================================================" -ForegroundColor Cyan
}
catch {
    Write-Host ""
    Write-Host "[오류] 작업 생성 실패: $_" -ForegroundColor Red
    Write-Host "수동으로 작업 스케줄러를 열어 설정해주세요." -ForegroundColor Red
}

Write-Host ""
Write-Host "즉시 테스트 백업을 실행하시겠습니까? (Y/N): " -NoNewline -ForegroundColor Green
$testNow = Read-Host

if ($testNow -eq "Y" -or $testNow -eq "y") {
    Write-Host ""
    Write-Host "테스트 백업 실행 중..." -ForegroundColor Yellow
    & $backupScriptPath

    Write-Host ""
    Write-Host "백업 로그 확인:" -ForegroundColor Yellow
    Get-Content "D:\Project\ykp-dashboard\backups\backup_log.txt" -Tail 10
}

Write-Host ""
Write-Host "설정이 완료되었습니다!" -ForegroundColor Green
Write-Host "종료하려면 아무 키나 누르세요..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")