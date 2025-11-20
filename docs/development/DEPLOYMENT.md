# Deployment & Environment Configuration

## Environment Separation Strategy

The project uses a **2-tier environment structure** with automatic environment detection:
- **Local Development** (your machine) → Local PostgreSQL
- **Production** (Railway) → Supabase PostgreSQL

**How It Works**:
- Local: `.env` file with `APP_ENV=local` (Git-ignored, contains your local settings)
- Production: Railway environment variables (set in Railway Dashboard, not in Git)
- No manual toggling or comment switching required!

**Template Files**:
- `.env.example` - Local development template (Git tracked)
- `.env.production.example` - Railway production template (Git tracked)
- `.env` - Your actual local config (Git ignored, never committed)

**Why NOT `.env.local`?**
- ❌ Laravel only loads `.env.local` when `APP_ENV != production`
- ❌ Creates chicken-and-egg problem (need APP_ENV to load file that sets APP_ENV)
- ✅ Use `.env` for local (Git-ignored) + Railway variables for production (platform-managed)

## Local Development Environment

### Setup Steps
1. Copy template: `cp .env.example .env`
2. Generate key: `php artisan key:generate`
3. Update `DB_PASSWORD_LOCAL` with your PostgreSQL password
4. Start PostgreSQL: `postgresql-17.6-2-windows-x64-binaries/bin/pg_ctl.exe -D postgresql-data start`
5. Create database: `psql -U postgres -c "CREATE DATABASE ykp_dashboard_local;"`
6. Run migrations: `php artisan migrate`
7. Seed database: `php artisan db:seed`
8. Start dev server: `composer dev`

### Required Local Variables (`.env`)
```env
APP_NAME="YKP ERP (Local)"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

# Local PostgreSQL only
DB_CONNECTION=pgsql_local
DB_HOST_LOCAL=localhost
DB_PORT_LOCAL=5432
DB_DATABASE_LOCAL=ykp_dashboard_local
DB_USERNAME_LOCAL=postgres
DB_PASSWORD_LOCAL=1234

# File-based drivers for local development (faster, no DB dependency)
SESSION_DRIVER=file
SESSION_LIFETIME=120
CACHE_STORE=file
QUEUE_CONNECTION=sync

# Feature Flags (local testing)
FEATURE_EXCEL_INPUT=true
FEATURE_ADVANCED_REPORTS=true
FEATURE_UI_V2=false
FEATURE_SUPABASE_ENHANCED=false
```

### Why File-Based Drivers for Local?
- ✅ Faster development (no database queries for session/cache)
- ✅ Easier debugging (no cache interference)
- ✅ Sync queue for immediate execution (no queue worker needed)
- ⚠️ Production uses database drivers for distributed environments

### Local PostgreSQL Commands
```bash
# Start PostgreSQL server (Windows)
postgresql-17.6-2-windows-x64-binaries/bin/pg_ctl.exe -D postgresql-data start

# Stop PostgreSQL server
postgresql-17.6-2-windows-x64-binaries/bin/pg_ctl.exe -D postgresql-data stop

# Connect to local database
postgresql-17.6-2-windows-x64-binaries/bin/psql.exe -U postgres -d ykp_dashboard_local

# Check server status
postgresql-17.6-2-windows-x64-binaries/bin/pg_ctl.exe -D postgresql-data status
```

## Railway Production Environment

### ⚠️ CRITICAL: Never commit production credentials to Git!

### Setup in Railway Dashboard
**Location**: `Your Service > Variables`

1. Copy variables from `.env.production.example`
2. Set `DB_PASSWORD` in Railway Secrets (secure storage)
3. Generate `APP_KEY`:
   ```bash
   php artisan key:generate --show
   # Copy output to Railway APP_KEY variable
   ```
4. Railway auto-fills `RAILWAY_PUBLIC_DOMAIN` - use it for `APP_URL`

### Required Railway Variables
```env
# Application
APP_NAME="YKP ERP"
APP_ENV=production
APP_KEY=   # Generate: php artisan key:generate --show
APP_DEBUG=false
APP_URL=${RAILWAY_PUBLIC_DOMAIN}

# Supabase PostgreSQL (Connection Pooler)
DB_CONNECTION=pgsql
DB_HOST=aws-1-ap-southeast-1.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.qwafwqxdcfpqqwpmphkm
DB_PASSWORD=   # ⚠️ SET IN RAILWAY SECRETS!
DB_SSLMODE=require   # CRITICAL: SSL required

# Session/Cache/Queue use production DB
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

# Logging (stderr for Railway)
LOG_CHANNEL=stderr
LOG_LEVEL=error

# Feature Flags (production)
FEATURE_EXCEL_INPUT=true
FEATURE_ADVANCED_REPORTS=true
FEATURE_UI_V2=false
FEATURE_SUPABASE_ENHANCED=true
```

### Supabase Connection Details
- Get credentials from Supabase Dashboard: `Settings > Database`
- **Use Connection Pooler** (recommended for Railway serverless)
  - Pooler Host: `aws-1-ap-southeast-1.pooler.supabase.com`
  - Direct Host: `db.qwafwqxdcfpqqwpmphkm.supabase.co` (backup)
- **SSL Mode**: MUST be `require`
- **Port**: Always `5432`

### Railway Deployment Checklist
- [ ] All variables set in Railway dashboard
- [ ] `DB_PASSWORD` in Railway Secrets (not visible in logs)
- [ ] `APP_DEBUG=false` in production
- [ ] `DB_SSLMODE=require` for Supabase
- [ ] `LOG_LEVEL=error` (not debug)
- [ ] Supabase backup created before deployment
- [ ] Health check works: `/health.txt`

## Feature Flags Usage

Check feature availability in code:
```php
if (config('features.excel_input')) {
    // Excel-like input features
}
```

### Available Feature Flags
- `excel_input_form` - Excel-like bulk input interface (HQ/admin only)
- `advanced_reports` - Advanced reporting system (HQ only)
- `ui_v2` - Next-gen UI beta testing (developer/HQ only)
- `supabase_enhanced` - Enhanced Supabase integration features

## CI/CD Pipeline

### GitHub Actions Workflow (`.github/workflows/ci.yml`)

**Triggers**:
- Push to branches: `main`, `staging`, `develop`
- Pull requests to: `main`, `staging`

**Pipeline Stages**:

1. **Test Stage** (PostgreSQL 14 service container)
   - Environment: PHP 8.2 + Node 20 + PostgreSQL 14
   - Composer/npm dependency caching (faster builds)
   - Code formatting check: `./vendor/bin/pint --test`
   - Static analysis: `./vendor/bin/phpstan analyse --level=5`
   - Unit/Feature tests with coverage: `./vendor/bin/phpunit --coverage-clover`
   - E2E smoke tests: `npm run test:smoke`
   - Coverage upload to Codecov

2. **Deploy Staging** (on `staging` branch push)
   - Railway CLI deployment
   - Auto-deploy to staging environment
   - No manual approval required

3. **Deploy Production** (on `main` branch push)
   - **Requires manual approval** (`environment: production`)
   - Creates deployment package (excludes: `node_modules/`, `tests/`, `.git/`)
   - SCP to production server via SSH
   - Runs database migrations: `php artisan migrate --force`
   - Cache optimization: `php artisan config:cache`, `route:cache`, `view:cache`
   - Asset build: `npm run build`

**Required GitHub Secrets**:
```env
RAILWAY_TOKEN               # Railway deployment token
PRODUCTION_HOST             # Production server IP/domain
PRODUCTION_USER             # SSH username
PRODUCTION_PASSWORD         # SSH password
CODECOV_TOKEN              # Codecov upload token (optional)
```

**Artifact Uploads**:
- **Codecov**: Test coverage reports (all tests)
- **Playwright Reports**: HTML reports (on E2E test failures)

### CI Failure Debugging
```bash
# Replicate CI environment locally with Docker
docker run -it --rm \
  -v $(pwd):/app \
  -w /app \
  php:8.2-cli bash

# Inside container
composer install
./vendor/bin/pint --test                    # Check formatting
./vendor/bin/phpstan analyse --level=5      # Check static analysis
./vendor/bin/phpunit                        # Run tests
```

### CI Best Practices
- ✅ All tests must pass before merge
- ✅ Code formatting must pass (Laravel Pint)
- ✅ Static analysis must pass (PHPStan level 5)
- ✅ Smoke tests must pass for deployments
- ⚠️ Manual approval required for production deploys

## Production Deployment Commands

```bash
npm run predeploy              # Build assets + cache config/routes/views
npm run build                  # Production build only
php artisan config:cache       # Cache configuration
php artisan route:cache        # Cache routes
php artisan view:cache         # Cache views
```

## Database Migrations in Production

### 🚨 CRITICAL: Supabase Backup Before Migrations

**모든 마이그레이션 작업 전에 반드시 Supabase 백업을 수행하세요:**

#### 백업 절차 (마이그레이션 전 필수)
1. **Supabase 대시보드 접속**: https://supabase.com/dashboard
2. **프로젝트 선택**: YKP Dashboard 프로젝트
3. **Database → Backups 메뉴 선택**
4. **"Create backup" 또는 "Download backup" 클릭**
5. **백업 파일 다운로드 및 안전한 위치에 저장**
6. **백업 완료 후에만 마이그레이션 실행**

#### 백업 파일 명명 규칙
```
db_cluster-YYYY-MM-DD@HH-MM-SS.backup
예: db_cluster-2025-09-29@17-09-30.backup
```

### Automated Supabase Backup System (Windows)

**위치**: `D:\Project\ykp-dashboard\backups\`

#### Quick Backup Commands
```bash
# Manual full backup (recommended before migrations)
cd backups
backup_supabase.bat YOUR_PROJECT_ID

# Setup daily auto-backup (Task Scheduler)
setup_auto_backup.bat YOUR_PROJECT_ID

# Check backup status
check_backup_status.bat

# Restore from backup
restore_supabase.bat path\to\backup.sql
```

#### Available Backup Scripts
- **`backup_supabase.bat`** - Main backup script (full database dump)
- **`restore_supabase.bat`** - Restore from SQL dump file
- **`auto_backup_daily.bat`** - Daily automation script (for Task Scheduler)
- **`setup_auto_backup.bat`** - Setup Windows Task Scheduler for daily backups
- **`check_backup_status.bat`** - View backup history and status

#### Best Practices
1. **Before every migration**: Run `backup_supabase.bat`
2. **Daily automation**: Setup auto-backup with `setup_auto_backup.bat`
3. **Test restores**: Periodically test `restore_supabase.bat` with a backup file
4. **Keep multiple copies**: Store backups in cloud storage (OneDrive/Google Drive)

### ⚠️ Database Migration Rules
**절대 기존 데이터를 삭제하지 마세요:**
- ❌ `php artisan migrate:fresh` **사용 금지** - 모든 데이터가 삭제됩니다
- ❌ `php artisan migrate:fresh --seed` **사용 금지** - 데이터베이스가 초기화됩니다
- ✅ `php artisan migrate` - 새로운 마이그레이션만 실행
- ✅ `php artisan migrate:rollback` - 마지막 마이그레이션만 롤백
- 스키마 변경 시 ALTER TABLE 사용 (DROP TABLE 금지)
- 🚨 **Supabase 프로덕션 환경에서는 위 백업 절차 완료 후에만 마이그레이션 실행**

---

**Back to**: [Development Docs Index](README.md) | [CLAUDE.md](../../CLAUDE.md)
