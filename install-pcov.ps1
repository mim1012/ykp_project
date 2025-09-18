# PowerShell script to install PCOV for Windows
# Run as Administrator

Write-Host "====================================" -ForegroundColor Cyan
Write-Host "PCOV Installation for PHP 8.4 Windows" -ForegroundColor Cyan
Write-Host "====================================" -ForegroundColor Cyan
Write-Host ""

# Check PHP version
$phpVersion = php -r "echo PHP_VERSION;"
Write-Host "PHP Version: $phpVersion" -ForegroundColor Yellow

# Get PHP extension directory
$extDir = php -r "echo ini_get('extension_dir');"
Write-Host "PHP Extension Directory: $extDir" -ForegroundColor Yellow

# Get PHP ini file location
$iniFile = php -r "echo php_ini_loaded_file();"
Write-Host "PHP INI File: $iniFile" -ForegroundColor Yellow

Write-Host ""
Write-Host "Manual Installation Steps:" -ForegroundColor Green
Write-Host "1. Download PCOV DLL from: https://windows.php.net/downloads/pecl/releases/pcov/1.0.11/" -ForegroundColor White
Write-Host "2. Choose: php_pcov-1.0.11-8.4-nts-vs16-x64.zip" -ForegroundColor White
Write-Host "3. Extract php_pcov.dll to: $extDir" -ForegroundColor White
Write-Host "4. Add to php.ini:" -ForegroundColor White
Write-Host "   extension=pcov" -ForegroundColor Yellow
Write-Host "   pcov.enabled=1" -ForegroundColor Yellow
Write-Host "   pcov.directory=." -ForegroundColor Yellow
Write-Host "5. Restart your web server" -ForegroundColor White
Write-Host ""

# Alternative: Try to download automatically
$downloadUrl = "https://windows.php.net/downloads/pecl/releases/pcov/1.0.11/php_pcov-1.0.11-8.4-nts-vs16-x64.zip"
$tempFile = "$env:TEMP\pcov.zip"

Write-Host "Attempting automatic download..." -ForegroundColor Cyan
try {
    Invoke-WebRequest -Uri $downloadUrl -OutFile $tempFile
    Write-Host "Download successful! File saved to: $tempFile" -ForegroundColor Green
    Write-Host "Please extract php_pcov.dll from the zip file and copy to: $extDir" -ForegroundColor Yellow
} catch {
    Write-Host "Automatic download failed. Please download manually." -ForegroundColor Red
}

Write-Host ""
Write-Host "After installation, verify with:" -ForegroundColor Cyan
Write-Host "php -m | findstr pcov" -ForegroundColor White