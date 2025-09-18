@echo off
echo ====================================
echo Setting up Git Branching Strategy
echo ====================================
echo.

echo Creating develop branch...
git checkout -b develop
echo ✓ develop branch created

echo.
echo Creating staging branch...
git checkout -b staging
echo ✓ staging branch created

echo.
echo Pushing branches to origin...
git push -u origin develop
git push -u origin staging

echo.
echo Current branch structure:
git branch -a

echo.
echo ====================================
echo Branch Protection Rules (GitHub)
echo ====================================
echo.
echo Please configure in GitHub Settings:
echo.
echo [main branch]
echo - Require pull request reviews (2)
echo - Require status checks
echo - Include administrators
echo.
echo [staging branch]
echo - Require pull request reviews (1)
echo - Require status checks
echo.
echo [develop branch]
echo - Require status checks
echo - Require linear history
echo.
echo ====================================
echo Setup complete!
echo ====================================