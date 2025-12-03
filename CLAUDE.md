# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

YKP ERP Dashboard - Laravel 12 + React 18 + PostgreSQL sales management system with RBAC (headquarters/branch/store roles).

## Quick Start

```bash
composer dev              # Full stack (Laravel + Queue + Vite)
simple-start.bat          # Laravel only (Windows)
composer quality          # Format + Analyse + Test (full CI check)

# URLs
http://localhost:8000     # Laravel
http://localhost:5173     # Vite HMR
```

## Critical Rules

### Database
- **ALWAYS backup before migrations**: `cd backups && backup_supabase.bat`
- **NEVER use `migrate:fresh` in production**
- Use `php artisan migrate` for new migrations, `migrate:rollback` to undo

### Git Workflow
- Branch naming: `claude/*` (Claude Code), `cursor/*` (Cursor AI)
- Commit format: `🤖 feat(scope): description`
- See: [docs/development/GIT_WORKFLOW.md](docs/development/GIT_WORKFLOW.md)

### Test Accounts
```
store@ykp.com    / password  (Store)
branch@ykp.com   / password  (Branch)
admin@ykp.com    / password  (Headquarters)
```

## Architecture

### Backend
- **Business Logic**: `app/Application/Services/`
- **SalesCalculator**: Core engine at `app/Helpers/SalesCalculator.php`
- **RBAC Middleware**: Role-based access (headquarters > branch > store)
- **API Routes**: `routes/api.php` (900+ lines, all major endpoints)

### Frontend
- React 18 + TanStack Query + AG-Grid (Excel-like data entry)
- TailwindCSS v4 + Vite

### Database
- Local: PostgreSQL (`ykp_dashboard_local`)
- Production: Supabase PostgreSQL via Railway
- Testing: SQLite in-memory

## Key Calculation Formula

**⚠️ Deduction (S) is SUBTRACTED, not added!**

```
Total Rebate (T) = K + L + M + N + O
Settlement (U)   = T - P + Q - R - S + W - X
```

Tax is currently disabled (V=0), so Margin = Settlement.
See: [docs/development/CALCULATIONS.md](docs/development/CALCULATIONS.md)

## Commands

```bash
# Testing
composer test                                      # PHPUnit tests
./vendor/bin/phpunit --filter="SalesCalculatorTest"  # Single test
npx playwright test --headed                       # E2E with browser

# Code Quality
composer format      # Laravel Pint
composer analyse     # PHPStan (level max)

# Database
php artisan migrate
php artisan queue:listen

# Clear caches (after .env changes)
php artisan config:clear && php artisan cache:clear

# PostgreSQL (Windows)
postgresql-17.6-2-windows-x64-binaries/bin/pg_ctl.exe -D postgresql-data start
```

## Windows Batch Files

```bash
simple-start.bat       # Start Laravel
quick-test.bat         # Run tests
backup-current-data.bat # Backup local DB
```

## Key Files

| File | Purpose |
|------|---------|
| `app/Helpers/SalesCalculator.php` | Core calculation engine |
| `app/Application/Services/SaleService.php` | Sales business logic |
| `routes/api.php` | All API endpoints |
| `config/features.php` | Feature flags |
| `config/sales.php` | Business rules |

## Environment Setup

**Local** (`.env`):
```env
APP_ENV=local
DB_CONNECTION=pgsql_local
SESSION_DRIVER=file
CACHE_STORE=file
```

**Production**: See [docs/development/DEPLOYMENT.md](docs/development/DEPLOYMENT.md)

## Claude Skills (Auto-activate)

5 skills in `.claude/skills/` activate automatically on relevant triggers:
- **env-health** - "설정이 반영 안돼", DB connection errors
- **env-switch** - "로컬로 전환", "Switch to production"
- **cache-guard** - After `.env` or config file changes
- **db-validate** - "Database hosts array is empty"
- **doc-sync** - "문서 업데이트해줘"

## Adding a New Sales Field

1. Add migration for database column
2. Update `SalesCalculator::computeRow()` mapping
3. Add field to AG-Grid column definitions
4. Update API validation in `SalesApiController`
5. Add to test fixtures

## Documentation

- [API_GUIDE.md](docs/development/API_GUIDE.md) - API architecture
- [CALCULATIONS.md](docs/development/CALCULATIONS.md) - Formula details
- [DEPLOYMENT.md](docs/development/DEPLOYMENT.md) - Environment setup
- [GIT_WORKFLOW.md](docs/development/GIT_WORKFLOW.md) - AI branching strategy
- [SKILLS.md](docs/development/SKILLS.md) - Claude Skills guide
- [TESTING.md](docs/development/TESTING.md) - Testing strategy
