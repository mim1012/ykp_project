@echo off
echo ===================================
echo GitHub Repository Setup for YKP Dashboard
echo ===================================
echo.

echo [Step 1] Adding files to git...
git add .
git commit -m "Initial commit: YKP Dashboard with complete test suite and CI/CD"

echo.
echo [Step 2] Create repository on GitHub first, then run:
echo.
echo git remote add origin https://github.com/YOUR_USERNAME/ykp-dashboard.git
echo git branch -M main
echo git push -u origin main
echo.
echo [Step 3] After pushing, GitHub Actions will:
echo - Run code formatting checks
echo - Execute static analysis
echo - Run unit tests
echo - Execute E2E tests
echo - Generate coverage reports
echo.
echo [Step 4] Configure repository settings:
echo 1. Go to Settings > Branches
echo 2. Add branch protection rule for 'main':
echo    - Require PR reviews
echo    - Require status checks (CI Pipeline)
echo    - Dismiss stale reviews
echo    - Include administrators
echo.
echo [Step 5] Configure secrets for deployment:
echo Go to Settings > Secrets and add:
echo - RAILWAY_TOKEN (for staging deployment)
echo - PRODUCTION_SERVER_HOST
echo - PRODUCTION_SERVER_USER
echo - PRODUCTION_SERVER_PASSWORD
echo.
echo ===================================