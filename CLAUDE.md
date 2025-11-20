# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

YKP ERP Dashboard - A Laravel-based sales management system with React frontend, implementing role-based access control (RBAC) for headquarters, branches, and stores.

**Tech Stack**: Laravel 12 + React 18 + PostgreSQL + Filament v4

## Quick Start

```bash
# Start development
composer dev                    # Full stack (Laravel + Queue + Vite)
simple-start.bat               # Laravel only (Windows)

# Testing
composer quality               # Format + Analyse + Test
npm run test:smoke             # E2E smoke tests

# Local URLs
http://localhost:8000          # Laravel application
http://localhost:5173          # Vite dev server (HMR)
```

## 🚨 Critical Rules

### Database Migrations
⚠️ **ALWAYS backup Supabase before migrations**: `cd backups && backup_supabase.bat`

**Migration Rules**:
- ❌ **NEVER use `migrate:fresh` in production** - data will be lost!
- ✅ Use `php artisan migrate` for new migrations only
- ✅ Use `php artisan migrate:rollback` to undo last batch
- ✅ ALTER TABLE for schema changes (never DROP TABLE)

### Git Workflow
- Use **AI-specific branches**: `claude/*` for Claude Code, `cursor/*` for Cursor AI
- Commit format: `🤖 feat(scope): description` (Claude) or `🔮 style(scope): description` (Cursor)
- See: [docs/development/GIT_WORKFLOW.md](docs/development/GIT_WORKFLOW.md)

### Test Accounts (Local Development)
```
Store:        store@ykp.com   / password
Branch:       branch@ykp.com  / password
Headquarters: admin@ykp.com   / password
```

## Architecture

### Backend
- **Application Services**: Business logic in `app/Application/Services/`
- **SalesCalculator**: Core calculation engine (`app/Helpers/SalesCalculator.php`)
- **RBAC**: Role-based middleware (headquarters/branch/store)
- **Queue System**: Database driver for async processing

### Frontend
- **React 18** with TanStack Query for API state
- **AG-Grid** for Excel-like bulk data entry
- **TailwindCSS v4** for styling
- **Code splitting** via Vite for performance

### Database
- **Local**: PostgreSQL (ykp_dashboard_local)
- **Production**: Supabase PostgreSQL (Railway deployment)
- **Testing**: SQLite in-memory (PHPUnit)

## Key Calculations

**⚠️ IMPORTANT**: Deduction (S) is **SUBTRACTED**, not added!

```
Total Rebate (T) = K + L + M + N + O
Settlement (U)   = T - P + Q + R - S + W - X
Tax (V)          = 0  [Currently disabled]
Margin Before (Y) = U  [Currently = Settlement]
Margin After (Z)  = U  [Currently = Settlement]
```

**See**: [docs/development/CALCULATIONS.md](docs/development/CALCULATIONS.md) for details

## Development Commands

```bash
# Testing
composer test                  # Run all PHPUnit tests
./vendor/bin/phpunit --filter="SalesCalculatorTest"  # Specific test
npx playwright test --headed   # E2E with browser UI

# Code Quality
composer format                # Auto-fix code style (Laravel Pint)
composer analyse               # Run PHPStan (level 5)

# Database
php artisan migrate            # Run pending migrations
php artisan db:seed            # Seed database
php artisan queue:listen       # Start queue worker

# PostgreSQL (Windows)
postgresql-17.6-2-windows-x64-binaries/bin/pg_ctl.exe -D postgresql-data start
postgresql-17.6-2-windows-x64-binaries/bin/psql.exe -U postgres -d ykp_dashboard_local
```

## Windows Development Shortcuts

Convenient `.bat` files in the root directory:

```bash
# Development
simple-start.bat              # Start Laravel only
run-laravel-only.bat          # Laravel without Vite

# Testing
quick-test.bat                # Run PHPUnit tests
test-system.bat               # System verification

# Database
backup-current-data.bat       # Local database backup
setup-branches.bat            # Initialize branch data

# Troubleshooting
fix-react-error.bat           # Fix React build errors
```

## File Structure

### Key Directories
```
app/
├── Application/Services/   # Business logic services
├── Helpers/                # SalesCalculator, FieldMapper, etc.
├── Http/Controllers/Api/   # API controllers
└── Jobs/                   # Background job classes

resources/js/
├── components/             # React components
├── pages/                  # Page-level components
└── utils/                  # Frontend utilities

tests/
├── Unit/                   # Business logic tests
├── Feature/                # API endpoint tests
└── playwright/             # E2E tests (100+ specs)
```

### Important Files
- `app/Helpers/SalesCalculator.php` - Core calculation engine
- `app/Application/Services/SaleService.php` - Sales business logic
- `routes/api.php` - API endpoints
- `config/features.php` - Feature flags
- `config/sales.php` - Business rules

## Environment Configuration

### Local Development (`.env`)
```env
APP_ENV=local
DB_CONNECTION=pgsql_local
DB_HOST_LOCAL=localhost
DB_DATABASE_LOCAL=ykp_dashboard_local
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

### Production (Railway Variables)
```env
APP_ENV=production
DB_CONNECTION=pgsql
DB_HOST=aws-1-ap-southeast-1.pooler.supabase.com
DB_SSLMODE=require
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

**See**: [docs/development/DEPLOYMENT.md](docs/development/DEPLOYMENT.md) for detailed setup

## Available Claude Skills

The project includes **5 Claude Skills** that activate automatically:

1. **env-health** - Environment health checker (config, cache, DB connection)
2. **env-switch** - Environment switcher (local ↔ production)
3. **cache-guard** - Laravel cache management
4. **db-validate** - Database connection validator
5. **doc-sync** - Documentation synchronization

**See**: [docs/development/SKILLS.md](docs/development/SKILLS.md) for usage guide

## Common Development Tasks

### Adding a New Sales Field
1. Add migration for database column
2. Update `SalesCalculator::computeRow()` mapping
3. Add field to AG-Grid column definitions
4. Update API validation rules in `SalesApiController`
5. Add to test fixtures in `tests/`

### Debugging Calculation Issues
1. Check `storage/logs/laravel.log` for calculation errors
2. Use `./vendor/bin/phpunit --filter="SalesCalculatorTest"` for testing
3. Enable SQL query logging: `DB_LOG_QUERIES=true` in `.env`
4. Check browser console for API response details

## Important Coding Guidelines

### File Creation Policy
- ❌ **NEVER create files unless absolutely necessary**
- ✅ **ALWAYS prefer editing existing files**
- ❌ **NEVER proactively create documentation files** (*.md)
- ✅ **Only create documentation files if explicitly requested**

### Code Modification Approach
1. Read the existing file first to understand current implementation
2. Make targeted edits to existing files rather than creating new ones
3. Maintain consistency with existing code style and patterns
4. Follow the project's established architectural patterns

## Quick Reference

### Testing
```bash
composer quality              # Format + Analyse + Unit + Feature tests
npm run test:smoke            # E2E smoke tests only
npx playwright test           # All E2E tests (100+ specs)
```

### Git Workflow
```bash
# Claude Code workflow
git checkout -b claude/feature-name
git commit -m "🤖 feat(sales): add feature"
git push origin claude/feature-name
# Create PR → main

# See: docs/development/GIT_WORKFLOW.md for details
```

### Database Backup
```bash
# Before migrations!
cd backups
backup_supabase.bat YOUR_PROJECT_ID
```

### Clear Caches
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

## Documentation Index

Detailed guides available in `docs/development/`:

- **[API_GUIDE.md](docs/development/API_GUIDE.md)** - API architecture, endpoints, helper classes
- **[CALCULATIONS.md](docs/development/CALCULATIONS.md)** - Detailed calculation formulas
- **[DEPLOYMENT.md](docs/development/DEPLOYMENT.md)** - Environment setup, CI/CD pipeline
- **[GIT_WORKFLOW.md](docs/development/GIT_WORKFLOW.md)** - AI-optimized branching strategy
- **[SKILLS.md](docs/development/SKILLS.md)** - Claude Skills usage guide
- **[TESTING.md](docs/development/TESTING.md)** - Testing strategy and best practices

---

**Last Updated**: 2025-11-20 | **Project**: YKP ERP Dashboard | **Stack**: Laravel 12 + React 18

**For detailed documentation, see**: [docs/development/README.md](docs/development/README.md)
