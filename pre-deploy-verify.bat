@echo off
REM Pre‑Deploy Verification Script (Windows)
REM Usage: Run on staging before production deploy.

SETLOCAL ENABLEDELAYEDEXPANSION

echo [1/7] Checking environment...
echo APP_ENV=%APP_ENV%

echo [2/7] Installing Node dependencies...
call npm ci || goto :error

echo [3/7] Installing Playwright browsers...
call npx playwright install || goto :error

echo [4/7] Migrating database (use --force on production)...
php artisan migrate || goto :error

echo [5/7] Running smoke tests (non‑production only)...
call npx playwright test -g "Deploy Smoke" || goto :error

echo [6/7] Building frontend assets...
call npm run build || goto :error

echo [7/7] Caching config/routes/views...
php artisan config:cache && php artisan route:cache && php artisan view:cache || goto :error

echo ✅ Pre‑deploy verification completed successfully.
exit /b 0

:error
echo ❌ Pre‑deploy verification failed. See logs above.
exit /b 1

