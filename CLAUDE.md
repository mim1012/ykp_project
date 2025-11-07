# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

YKP ERP Dashboard - A Laravel-based sales management system with React frontend, implementing role-based access control (RBAC) for headquarters, branches, and stores. The system features real-time sales calculations, bulk data entry via Excel-like interfaces, and comprehensive financial reporting.

## Architecture

### Backend Stack
- **Laravel 12** with PHP 8.2+
- **Filament v4** for admin panels
- **PostgreSQL** (production) / **SQLite in-memory** (testing via PHPUnit)
- **Application Service Pattern** in `app/Application/Services/`
- **Job Queue System** for background processing (database driver)
- **Laravel Excel (Maatwebsite)** for import/export functionality
- **Sentry** for error tracking and monitoring

### Frontend Stack
- **Vite** build system with React integration
- **React 18** with hooks and context API
- **TailwindCSS v4** for styling
- **AG-Grid** for Excel-like bulk data entry
- **Chart.js** for data visualization
- **TanStack Query** for API state management

### Key Architectural Patterns
- **Domain-Driven Design**: Business logic in Application Services
- **RBAC System**: Role-based middleware (headquarters/branch/store)
- **API-First Design**: RESTful APIs with consistent response format
- **Feature Flags**: Environment-based feature toggling
- **Real-time Calculations**: SalesCalculator helper for margin calculations

## Development Commands

### Quick Start
```bash
# Full development environment (recommended)
composer dev                    # Runs PHP server + queue worker + Vite concurrently
composer dev-with-logs          # Same as above + Laravel Pail for logs

# Individual services
php artisan serve               # Laravel dev server
php artisan queue:listen        # Queue worker
npm run dev                     # Vite dev server
```

### Windows Development Shortcuts

The project includes convenient `.bat` files in the root directory for quick operations:

#### Development Servers
```bash
simple-start.bat              # Start Laravel only (fastest for backend work)
run-laravel-only.bat          # Laravel without Vite
run-without-vite.bat          # Backend only mode
# Alternatively: composer dev  # Full stack (Laravel + Queue + Vite)
```

#### Testing & Quality
```bash
quick-test.bat                # Run PHPUnit tests quickly
run-tests.bat                 # Full test suite
test-system.bat               # System verification tests
```

#### Database Operations
```bash
backup-current-data.bat       # Quick local database backup
migrate-to-supabase.bat       # Migration helper for Supabase
setup-branches.bat            # Initialize branch data
```

#### Deployment
```bash
pre-deploy-verify.bat         # Pre-deployment checks
```

#### Troubleshooting
```bash
fix-react-error.bat           # Fix common React build errors
```

**Tip**: Double-click any `.bat` file to run, or execute from command line in project root.

### Testing
```bash
# Backend tests
composer test                   # Run all PHPUnit tests
composer quality                # Full quality check (format + analyze + test)
./vendor/bin/phpunit --filter="TestClassName"  # Run specific test class
./vendor/bin/phpunit --filter="testMethodName" # Run specific test method

# Frontend E2E tests
npm run test:smoke              # Quick smoke tests for deployment
npx playwright test             # Full Playwright suite
npx playwright test --headed    # With browser UI
npx playwright test tests/playwright/specific-test.spec.js  # Run specific test file
```

### Code Quality
```bash
composer format                 # Fix code style with Laravel Pint
composer format-check          # Check style without changes
composer analyse               # Run PHPStan static analysis (level 5)
./vendor/bin/phpstan analyse --level=5 app/Helpers/SalesCalculator.php  # Analyze specific file
./vendor/bin/pint app/         # Format specific directory
```

### Database Management
```bash
php artisan migrate:fresh      # Reset database (dev only)
php artisan migrate:fresh --seed  # Reset with seeders
php artisan db:seed            # Run seeders only
php artisan migrate            # Run pending migrations
php artisan migrate:rollback   # Rollback last migration batch
php artisan migrate:status     # Check migration status
php artisan db:show            # Show database information
php artisan db:table sales     # Show specific table structure
```

### Local PostgreSQL Setup
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

### Production Deployment
```bash
npm run predeploy              # Build assets + cache config/routes/views
npm run build                  # Production build only
php artisan config:cache       # Cache configuration
php artisan route:cache        # Cache routes
php artisan view:cache         # Cache views
```

### CI/CD Pipeline

#### GitHub Actions Workflow (`.github/workflows/ci.yml`)

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

**CI Failure Debugging**:
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

**CI Best Practices**:
- ✅ All tests must pass before merge
- ✅ Code formatting must pass (Laravel Pint)
- ✅ Static analysis must pass (PHPStan level 5)
- ✅ Smoke tests must pass for deployments
- ⚠️ Manual approval required for production deploys

## Database Schema

### Core Tables
- **users**: System users with role-based access (roles: headquarters, branch, store)
- **branches**: Regional management units
- **stores**: Individual retail locations
- **sales**: Transaction records with calculated margins
- **monthly_settlements**: Month-end financial reconciliation
- **dealer_profiles**: Calculation configurations per dealer
- **jobs**: Queue system for background processing
- **cache**: Database-backed cache storage
- **sessions**: Database-backed session storage

### Key Relationships
```
Users → Branches (many-to-one)
Users → Stores (many-to-one)
Stores → Branches (many-to-one)
Sales → Stores (many-to-one)
Sales → DealerProfiles (for calculations)
MonthlySettlements → Sales (aggregates by period)
```

### Performance Indexes
- `sales`: Indexed on `store_id`, `sale_date`, `agency`, `branch_id`
- `users`: Indexed on role and relationship fields for faster RBAC queries

## API Architecture

### Authentication & Authorization
- **Session-based authentication** for web routes
- **CSRF protection** enabled for state-changing operations
- **RBAC Middleware** (`RBACMiddleware`) enforces role-based access
- **Policies**: `SalePolicy`, `UserPolicy` for fine-grained authorization
- **API Authentication Middleware** (`ApiAuthenticate`) for API routes

### Middleware Stack
- `RBACMiddleware`: Role-based access control (headquarters/branch/store)
- `PerformanceMonitoringMiddleware`: Tracks API performance metrics
- `TrustProxies`: Handles proxy headers for Railway deployment
- `VerifyCsrfToken`: CSRF protection with API route exceptions
- `DisableTimebox`: Disables time-based throttling for development

### API Response Format
```php
// Success response
['success' => true, 'data' => mixed, 'meta' => array]

// Error response
['success' => false, 'error' => string, 'code' => int]
```

### Key API Endpoints
```
GET  /api/dashboard/overview         # Dashboard statistics
POST /api/sales/bulk-save           # Bulk sales import
POST /api/calculation/profile/row   # Real-time margin calculation
GET  /api/monthly-settlements       # Financial reconciliation
GET  /api/statistics/sales          # Sales statistics with filters
POST /api/users                     # User management (HQ only)
POST /api/stores/bulk-create        # Bulk store creation (queued)
POST /api/branches/bulk-create      # Bulk branch creation (queued)
GET  /api/stores/export/template    # Download store import template
GET  /api/branches/export/template  # Download branch import template
```

## Core Helper Classes

### SalesCalculator (`app/Helpers/SalesCalculator.php`)
The primary calculation engine for the system:
- Implements Excel-like calculation formulas
- Real-time margin calculations based on dealer profiles
- Handles both basic and profile-based calculations
- Tax rate: 10% (constant: TAX_RATE)
- Key calculations: total_rebate, settlement, tax, margin_before, margin_after

### FieldMapper (`app/Helpers/FieldMapper.php`)
Maps between different field naming conventions:
- Handles multiple input formats (English/Korean field names)
- Converts between Excel column letters and field names
- Supports legacy field names for backward compatibility
- Essential for Excel import/export functionality

### DatabaseHelper (`app/Helpers/DatabaseHelper.php`)
Database utility functions:
- Connection testing and validation
- Query optimization helpers
- Database state management utilities

### RandomDataGenerator (`app/Helpers/RandomDataGenerator.php`)
Generates realistic test data:
- Creates sample sales records for testing
- Generates mock data for development
- Used in seeders and test factories

### Calculation Fields Mapping
```php
// Input fields (supports multiple naming conventions)
price_setting/base_price     → K (액면가/셋팅가)
verbal1                      → L (구두1)
verbal2                      → M (구두2)
grade_amount                 → N (그레이드)
addon_amount/additional_amount → O (부가추가)
paper_cash/cash_activation   → P (서류상현금개통)
usim_fee                     → Q (유심비)
new_mnp_disc/new_mnp_discount → R (신규/MNP할인)
deduction                    → S (차감)
cash_in/cash_received        → W (현금받음)
payback                      → X (페이백)

// Calculated fields (ACTUAL IMPLEMENTATION)
total_rebate = K+L+M+N+O     → T (리베총계)
settlement = T-P+Q+R-S+W-X   → U (정산금) ⚠️ NOTE: Deduction is SUBTRACTED
tax = U × 0.10               → V (세금) [Currently DISABLED - returns 0]
margin_before = U-V          → Y (세전마진) [Currently equals U since tax=0]
margin_after = U             → Z (세후마진) [Currently equals settlement]
```

**IMPORTANT CALCULATION NOTES:**
- **Deduction (S) is SUBTRACTED** from settlement, not added: `U = T - P + Q + R - S + W - X`
- **Tax calculation is currently DISABLED** - always returns 0 in production
- **Settlement = Margin (both before/after tax)** because tax is not applied
- When tax is re-enabled, formulas will be: `Y = U - V` and `Z = Y`

## Background Jobs & Queue System

The system uses Laravel's queue system with database driver for asynchronous processing:

### Job Classes
- **`ProcessBatchCalculationJob`**: Handles bulk sales calculations (>100 rows)
- **`ProcessBulkStoreCreationJob`**: Processes bulk store imports from Excel/CSV
- **`ProcessBulkBranchCreationJob`**: Processes bulk branch imports from Excel/CSV

### Queue Management
```bash
php artisan queue:listen --tries=1   # Start queue worker
php artisan queue:work              # Process single batch
php artisan queue:failed            # View failed jobs
php artisan queue:retry all         # Retry failed jobs
```

### Excel Import/Export

#### Import Classes (`app/Imports/`)
- **`StoresBulkImport`**: Handles Excel/CSV imports for bulk store creation with automatic user account generation

#### Export Classes (`app/Exports/`)
- **`StoreTemplateExport`**: Generates Excel template for store import
- **`BranchTemplateExport`**: Generates Excel template for branch import
- **`StoreAccountsExport`**: Exports store account details
- **`CreatedStoreAccountsExport`**: Exports newly created store accounts
- **`StoreStatisticsExport`**: Exports store-level statistics

## Application Services Pattern

The system follows Domain-Driven Design principles with dedicated service classes in `app/Application/Services/`:

### Service Classes
- **`SaleService`**: Core business logic for sales operations
  - Bulk operations and CRUD
  - Data validation and normalization
  - Integration with SalesCalculator
- **`MonthlySettlementService`**: Financial reconciliation logic
  - Month-end settlement processing
  - Aggregation and reporting
- **`RefundService`**: Handles refund processing
- **`ExpenseService`**: Expense tracking and management
- **`PayrollService`**: Payroll calculations

### Usage Pattern
Controllers delegate to services for all business logic:
```php
// Controller
public function store(Request $request) {
    return $this->saleService->createSale($request->validated());
}

// Service contains actual business logic
class SaleService {
    public function createSale(array $data) {
        // Validation, calculation, persistence
    }
}
```

## Role-Based Features

### Headquarters (role: headquarters)
- Full system access including user management
- Cross-branch reporting and analytics
- Dealer profile management
- System-wide statistics and reports
- Bulk data import/export capabilities

### Branch (role: branch)
- Branch-specific data access only
- Store management within their branch
- Branch-level reporting and analytics
- Cannot access other branches' data
- Limited bulk operations for owned stores

### Store (role: store)
- Store-specific sales input only
- Basic reporting for their store
- No access to branch or HQ features
- Limited to their own sales data
- Cannot perform bulk operations

## Testing Strategy

### Unit Tests (`tests/Unit/`)
Focus on business logic, especially:
- `SalesCalculatorTest.php` - Core calculation engine (formulas, margins, taxes)
- `UserPermissionTest.php` - RBAC permission validation
- `DashboardControllerTest.php` - Dashboard statistics logic
- `DeductionCalculationTest.php` - Deduction formula edge cases

**Run Unit Tests**:
```bash
./vendor/bin/phpunit --testsuite=Unit
./vendor/bin/phpunit tests/Unit/SalesCalculatorTest.php  # Single test file
```

### Feature Tests (`tests/Feature/`)
API endpoint testing with database:
- `SaleBulkCreateTest.php.pest` - Bulk sales creation API (Pest PHP)
- `SaleStatisticsTest.php.pest` - Statistics aggregation API (Pest PHP)
- `StatisticsApiTest.php` - Statistics endpoint responses
- `CalculationApiPerformanceTest.php` - Performance benchmarks for calculations
- `ChangePasswordTest.php` - Password change E2E flow

**Run Feature Tests**:
```bash
./vendor/bin/phpunit --testsuite=Feature
./vendor/bin/pest                                        # Run all Pest tests
./vendor/bin/pest tests/Feature/SaleBulkCreateTest.php  # Single Pest test
```

### E2E Tests (`tests/playwright/`)

**Total**: 100+ Playwright spec files covering complete workflows

#### Smoke Tests (Tagged with `@smoke`)
Critical post-deployment checks:
- `deploy-smoke.spec.js` - Basic app functionality verification

**Run Smoke Tests**:
```bash
npm run test:smoke                           # Quick smoke test suite
npx playwright test --grep=@smoke            # Alternative
```

#### RBAC Tests
Role-based access control enforcement:
- `comprehensive-rbac-test.spec.js` - Full RBAC matrix testing
- `role-based-access-test.spec.js` - Role-specific feature access
- `e2e-rbac-branch-restriction.spec.js` - Branch data isolation

**Run RBAC Tests**:
```bash
npx playwright test --grep="rbac"
npx playwright test tests/playwright/comprehensive-rbac-test.spec.js
```

#### Feature Tests
User workflow testing:
- `e2e-excel-bulk-paste.spec.js` - Excel paste functionality
- `e2e-password-change.spec.js` - Password change flow
- `e2e-sales-save-and-stats.spec.js` - Sales entry + statistics update

**Run Feature Tests**:
```bash
npx playwright test --grep="e2e-"
npx playwright test tests/playwright/e2e-excel-bulk-paste.spec.js
```

#### Performance Tests
Load and performance validation:
- `performance-large-dataset.spec.js` - Large data rendering
- `performance-security-test.spec.js` - Security + performance checks

**Run Performance Tests**:
```bash
npx playwright test --grep="performance"
```

#### Accessibility Tests
A11y and responsive design:
- `accessibility-keyboard-nav.spec.js` - Keyboard navigation
- `mobile-responsive.spec.js` - Mobile layout validation

**Run Accessibility Tests**:
```bash
npx playwright test --grep="accessibility|mobile"
```

### Test Configuration

**PHPUnit** (`phpunit.xml`):
- Database: SQLite in-memory (fast, isolated)
- Environment: Testing environment (`.env.testing`)
- Coverage: Clover format for CI/CD

**Playwright** (`playwright.config.js`):
- Timeout: 90 seconds per test
- Workers: 1 (sequential execution for consistency)
- Browsers: Chromium (default)
- Base URL: `http://localhost:8000`
- Screenshots: On failure only
- Video: On first retry

**Running All Tests**:
```bash
composer quality              # Format + Analyse + Unit + Feature tests
npm run test:smoke            # E2E smoke tests only
npx playwright test           # All E2E tests (100+ specs, ~30-60 min)
```

## Performance Optimizations

### Database
- Indexes on: store_id, sale_date, agency, branch_id
- Aggregate queries to avoid N+1 problems
- Query result caching for dashboard stats

### Frontend
- **Code splitting via Vite**: Manual chunks for vendor, animations, query, icons, virtualization, aggrid, utils
- **Virtual scrolling** in AG-Grid for large datasets
- **TanStack Query caching** with 5-minute stale time
- **Lazy loading** for images and heavy components
- **Terser minification** with console/debugger removal in production
- **ES2020 target** for modern browser features
- **Dependency pre-bundling** for faster dev server startup
- **HMR (Hot Module Replacement)** for instant updates during development

### API
- Rate limiting on calculation endpoints (60/minute)
- Background jobs for bulk operations > 100 rows
- Response caching for statistics (5-minute TTL)

## Environment Configuration

### Environment Separation Strategy

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

### Local Development Environment

**Setup**:
1. Copy template: `cp .env.example .env`
2. Generate key: `php artisan key:generate`
3. Update `DB_PASSWORD_LOCAL` with your PostgreSQL password
4. Start PostgreSQL: `postgresql-17.6-2-windows-x64-binaries/bin/pg_ctl.exe -D postgresql-data start`
5. Create database: `psql -U postgres -c "CREATE DATABASE ykp_dashboard_local;"`
6. Run migrations: `php artisan migrate`
7. Seed database: `php artisan db:seed`
8. Start dev server: `composer dev`

**Required Local Variables** (`.env`):
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

**Why File-Based Drivers for Local?**
- ✅ Faster development (no database queries for session/cache)
- ✅ Easier debugging (no cache interference)
- ✅ Sync queue for immediate execution (no queue worker needed)
- ⚠️ Production uses database drivers for distributed environments

### Railway Production Environment

**⚠️ CRITICAL: Never commit production credentials to Git!**

**Setup in Railway Dashboard** (`Your Service > Variables`):
1. Copy variables from `.env.production.example`
2. Set `DB_PASSWORD` in Railway Secrets (secure storage)
3. Generate `APP_KEY`:
   ```bash
   php artisan key:generate --show
   # Copy output to Railway APP_KEY variable
   ```
4. Railway auto-fills `RAILWAY_PUBLIC_DOMAIN` - use it for `APP_URL`

**Required Railway Variables**:
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

**Supabase Connection Details**:
- Get credentials from Supabase Dashboard: `Settings > Database`
- **Use Connection Pooler** (recommended for Railway serverless)
  - Pooler Host: `aws-1-ap-southeast-1.pooler.supabase.com`
  - Direct Host: `db.qwafwqxdcfpqqwpmphkm.supabase.co` (backup)
- **SSL Mode**: MUST be `require`
- **Port**: Always `5432`

**Railway Deployment Checklist**:
- [ ] All variables set in Railway dashboard
- [ ] `DB_PASSWORD` in Railway Secrets (not visible in logs)
- [ ] `APP_DEBUG=false` in production
- [ ] `DB_SSLMODE=require` for Supabase
- [ ] `LOG_LEVEL=error` (not debug)
- [ ] Supabase backup created before deployment
- [ ] Health check works: `/health.txt`

### Feature Flags Usage
Check feature availability in code:
```php
if (config('features.excel_input')) {
    // Excel-like input features
}
```

## Available Claude Skills

이 프로젝트에는 환경 설정 및 트러블슈팅을 자동화하는 **5개의 Claude Skills**가 설치되어 있습니다.

Skills는 **자동으로 실행**됩니다 - 사용자가 특정 문제를 언급하면 Claude가 관련 Skill을 자동으로 활성화합니다.

### Skill 1: env-health (Environment Health Checker) ⭐⭐⭐

**자동 실행 조건**:
- "설정이 반영 안돼" 또는 "configuration not applied" 언급
- "Database connection error", "SQLite connection attempt" 오류
- "Database hosts array is empty" 오류
- ".env 파일 수정했는데 변화 없음"

**주요 기능**:
- 환경 설정 (`.env`) 일관성 검증
- Laravel 캐시 상태 진단 및 자동 클리어
- DB 연결 테스트 (pgsql_local 확인)
- Session/Cache 드라이버 문제 감지

**해결하는 문제**:
- ✅ 캐시 때문에 설정 변경 무시됨
- ✅ 잘못된 DB 연결 (SQLite, Supabase 등)
- ✅ "Database hosts array is empty" 오류

**사용 예시**:
```
사용자: "왜 Laravel이 SQLite로 연결하려고 해?"
Claude: [env-health Skill 자동 실행]
         → .env 파일 분석
         → 캐시 상태 확인
         → 문제 발견: "config.php 캐시 파일 존재"
         → php artisan optimize:clear 실행
         → 서버 재시작 안내
```

---

### Skill 2: env-switch (Environment Switcher) ⭐⭐

**자동 실행 조건**:
- "로컬로 전환" / "Switch to local"
- "프로덕션으로 전환" / "Switch to production"
- "환경 분리" / "Separate environments"
- "주석 처리 말고 자동으로"

**주요 기능**:
- 현재 환경 분석 (APP_ENV, DB_CONNECTION)
- 목표 환경 설정 생성 및 검증
- 위험 감지 (프로덕션 DB 접근 시도 등)
- 단계별 전환 가이드 제공

**해결하는 문제**:
- ✅ 로컬/프로덕션 환경 수동 전환 번거로움
- ✅ 실수로 프로덕션 DB 접근 방지
- ✅ 올바른 환경 설정 템플릿 제공

**사용 예시**:
```
사용자: "로컬 개발 환경으로 전환하고 싶어"
Claude: [env-switch Skill 자동 실행]
         → 현재 환경 확인 (production 감지)
         → ⚠️ 경고: 프로덕션 설정 사용 중!
         → 로컬 설정 템플릿 제공
         → 후속 조치 안내 (캐시 클리어, 서버 재시작)
```

---

### Skill 3: cache-guard (Laravel Cache Guardian) ⭐⭐

**자동 실행 조건**:
- `.env` 파일 수정 직후
- `config/*.php` 파일 수정 직후
- "설정이 반영 안돼" / "Changes not applied"
- "캐시 때문인가?"

**주요 기능**:
- 설정 파일 변경 감지
- 적절한 캐시 클리어 (config, cache, route, view)
- 서버 재시작 필요 여부 판단
- 캐시 상태 모니터링 및 보고

**해결하는 문제**:
- ✅ 설정 변경 후 캐시 클리어 깜빡함
- ✅ 서버 재시작 필요성 인지 못함
- ✅ 오래된 캐시 파일 사용 중

**사용 예시**:
```
[.env 파일 수정 후]
Claude: [cache-guard Skill 자동 실행]
         → 캐시 파일 존재 감지 (config.php)
         → php artisan config:clear 자동 실행
         → "서버 재시작 권장" 메시지 출력
         → 검증: 현재 설정 재확인
```

---

### Skill 4: db-validate (Database Connection Validator) ⭐

**자동 실행 조건**:
- "Database connection error" 오류
- "Database hosts array is empty" 오류
- "Could not connect to database" 오류
- "SQLSTATE[HY000]" 오류
- "DB 연결 확인해줘"

**주요 기능**:
- `.env` 파일 DB 설정 검증
- 실제 DB 연결 테스트 (tinker 사용)
- PostgreSQL 서버 상태 확인 (로컬)
- 로컬/프로덕션 DB 혼동 감지

**해결하는 문제**:
- ✅ DB 연결 설정 오류 진단
- ✅ PostgreSQL 서버 미실행 감지
- ✅ 비밀번호 불일치 또는 DB 미생성
- ✅ 프로덕션 DB 실수 접근 방지

**사용 예시**:
```
사용자: "Database hosts array is empty 오류 나는데?"
Claude: [db-validate Skill 자동 실행]
         → .env 파일 분석
         → SESSION_DRIVER=database 발견
         → SESSION_CONNECTION 없음 감지!
         → 권장: SESSION_DRIVER=file (로컬 개발)
         → 해결책 제시
```

---

### Skill 5: doc-sync (Documentation Sync) ⭐

**자동 실행 조건**:
- 주요 설정 변경 후
- 새로운 Skill 추가 후
- "문서 업데이트해줘" / "Update documentation"
- "CLAUDE.md에 반영해줘"
- ".env.example 최신화"

**주요 기능**:
- CLAUDE.md 업데이트 (환경 설정, Skills 목록)
- `.env.example` 템플릿 동기화
- 변경 내역 요약
- Git 커밋 메시지 자동 생성

**해결하는 문제**:
- ✅ 문서와 실제 설정 불일치
- ✅ 새로운 Skill 문서화 누락
- ✅ 커밋 메시지 작성 번거로움

**사용 예시**:
```
사용자: "CLAUDE.md에 지금까지 변경사항 반영해줘"
Claude: [doc-sync Skill 자동 실행]
         → 환경 설정 섹션 업데이트
         → Skills 목록 추가
         → Git 커밋 메시지 생성
         → 변경 내역 요약 보고
```

---

## Skills 사용 팁

### 자동 실행 vs 수동 호출

**Skills는 자동 실행됩니다** - 특별한 명령어 필요 없음!
```
❌ 잘못된 사용: "/env-health 실행해줘"
✅ 올바른 사용: "설정이 반영 안되는데?"
                → env-health Skill 자동 실행됨
```

### Skills 조합 사용

여러 Skills가 함께 작동할 수 있습니다:
```
1. .env 파일 수정
   → cache-guard: 캐시 클리어
   → env-health: 설정 검증
   → db-validate: DB 연결 테스트

2. 환경 전환
   → env-switch: 전환 가이드
   → cache-guard: 캐시 관리
   → doc-sync: 문서 업데이트
```

### Skills 위치

```
.claude/skills/
├── env-health/SKILL.md       # 환경 건강 진단
├── env-switch/SKILL.md       # 환경 전환
├── cache-guard/SKILL.md      # 캐시 관리
├── db-validate/SKILL.md      # DB 연결 검증
└── doc-sync/SKILL.md         # 문서 동기화
```

**팀 공유**: `.claude/skills/` 디렉토리가 Git에 포함되어 있어, 팀원들도 자동으로 사용 가능합니다.

### Custom Configuration Files

#### Feature Flags System (`config/features.php`)

The system uses a sophisticated feature flag configuration for gradual rollouts:

```php
// Check feature availability in code
if (config('features.features.excel_input_form.enabled')) {
    // Excel input UI features
}

// Check with role-based access
if (config('features.features.advanced_reports.allowed_roles.headquarters')) {
    // HQ-only advanced reports
}
```

**Available Feature Flags**:
- `excel_input_form` - Excel-like bulk input interface (HQ/admin only)
- `advanced_reports` - Advanced reporting system (HQ only)
- `ui_v2` - Next-gen UI beta testing (developer/HQ only)
- `supabase_enhanced` - Enhanced Supabase integration features

**Rollout Configuration Options**:
- `enabled` (boolean) - Master switch for the feature
- `rollout_percentage` (0-100) - Gradual rollout percentage
- `allowed_users` (array) - Email whitelist for specific users
- `allowed_roles` (array) - Role-based access control

**Example Feature Configuration**:
```php
'excel_input_form' => [
    'enabled' => true,
    'rollout_percentage' => 100,
    'allowed_roles' => ['headquarters', 'admin'],
]
```

#### Sales Business Rules (`config/sales.php`)

Business logic constants and validation rules:

```php
// Default sales targets when Goals table is empty
'default_targets' => [
    'system' => ['monthly_sales' => 50000000],  // 5천만원
    'branch' => ['monthly_sales' => 10000000],  // 1천만원
    'store' => ['monthly_sales' => 5000000],    // 500만원
]

// Data validation limits
'validation' => [
    'max_daily_records' => 100,    // Max sales records per day per store
    'max_bulk_records' => 1000,    // Max records in bulk operation
    'min_settlement_amount' => 0,   // Minimum settlement amount
]

// Performance settings
'performance' => [
    'cache_duration' => 300,        // Statistics cache TTL (5 minutes)
    'ranking_limit' => 10,          // Top N stores in rankings
    'batch_size' => 100,            // Records per batch in jobs
]

// Rate limiting
'rate_limits' => [
    'calculation' => 60,            // Calculation API calls per minute
    'bulk_save' => 10,              // Bulk save operations per minute
]
```

**Usage in Controllers**:
```php
$maxRecords = config('sales.validation.max_bulk_records');
$cacheMinutes = config('sales.performance.cache_duration');
```

## Important Development Guidelines

### 🚨 CRITICAL: Supabase Backup Before Migrations - 필수!
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

The project includes automated batch scripts for Supabase database backups:

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

#### Backup Files Generated
```
backups/
├── full_backup_YYYYMMDD_HHMMSS.sql     # Complete database dump
├── schema_backup_YYYYMMDD_HHMMSS.sql   # Schema only (no data)
├── sales_backup_YYYYMMDD_HHMMSS.sql    # Sales table backup
├── users_backup_YYYYMMDD_HHMMSS.sql    # Users table backup
└── backup_log.txt                       # Backup execution log
```

#### Best Practices
1. **Before every migration**: Run `backup_supabase.bat`
2. **Daily automation**: Setup auto-backup with `setup_auto_backup.bat`
3. **Test restores**: Periodically test `restore_supabase.bat` with a backup file
4. **Keep multiple copies**: Store backups in cloud storage (OneDrive/Google Drive)

### ⚠️ Database Migration Rules - 중요!
**절대 기존 데이터를 삭제하지 마세요:**
- ❌ `php artisan migrate:fresh` **사용 금지** - 모든 데이터가 삭제됩니다
- ❌ `php artisan migrate:fresh --seed` **사용 금지** - 데이터베이스가 초기화됩니다
- ✅ `php artisan migrate` - 새로운 마이그레이션만 실행
- ✅ `php artisan migrate:rollback` - 마지막 마이그레이션만 롤백
- 스키마 변경 시 ALTER TABLE 사용 (DROP TABLE 금지)
- 🚨 **Supabase 프로덕션 환경에서는 위 백업 절차 완료 후에만 마이그레이션 실행**

### 📍 Local Testing Information
**로컬 테스트 환경 접속 정보:**
- **Laravel 서버**: http://127.0.0.1:8000 또는 http://localhost:8000
- **서버 시작**: `php artisan serve` (기본 포트 8000)
- **Vite 개발 서버**: `npm run dev` (포트 5173, HMR 지원)
- **동시 실행**: `composer dev` (Laravel + Vite 함께 실행)

**테스트 계정 정보:**
- **매장 계정**: `store@ykp.com` / 비밀번호: `password`
- **지사 계정**: `branch@ykp.com` / 비밀번호: `password`
- **본사 계정**: `admin@ykp.com` / 비밀번호: `password`

## Common Development Tasks

### Adding a New Sales Field
1. Add migration for database column
2. Update `SalesCalculator::computeRow()` mapping
3. Add field to AG-Grid column definitions
4. Update API validation rules in `SalesApiController`
5. Add to test fixtures in `tests/`

### Creating a New Role-Based Feature
1. Define permission in `RoleMiddleware`
2. Add route with appropriate middleware
3. Update frontend route guards
4. Add E2E tests for all roles

### Debugging Calculation Issues
1. Check `storage/logs/laravel.log` for calculation errors
2. Use `./vendor/bin/phpunit --filter="SalesCalculatorTest"` for isolated testing
3. Enable SQL query logging in `.env` with `DB_LOG_QUERIES=true`
4. Check browser console for API response details

## File Structure

### Key Directories
```
app/
├── Application/Services/   # Business logic services (SaleService, MonthlySettlementService, etc.)
├── Auth/                  # Authentication-related classes
├── Exports/              # Excel export classes (Maatwebsite/Laravel Excel)
├── Imports/              # Excel import classes (Maatwebsite/Laravel Excel)
├── Helpers/              # Utilities (SalesCalculator, FieldMapper, DatabaseHelper)
├── Http/
│   ├── Controllers/Api/  # API controllers
│   └── Middleware/       # Custom middleware (RBAC, Performance, etc.)
├── Jobs/                 # Background job classes
├── Models/              # Eloquent models
├── Policies/            # Authorization policies
└── Filament/            # Filament admin panel resources
resources/
├── js/
│   ├── components/      # React components
│   ├── hooks/          # Custom React hooks
│   ├── pages/          # Page-level React components
│   ├── providers/      # Context providers
│   └── utils/          # Frontend utilities
└── views/              # Blade templates
tests/
├── Unit/               # Unit tests (SalesCalculator, permissions, etc.)
├── Feature/            # API tests (endpoints, RBAC enforcement)
└── playwright/         # E2E tests (user workflows)
database/
├── migrations/         # Database schema migrations
├── seeders/           # Database seeders
└── factories/         # Model factories for testing
```

### Important Files
- `app/Helpers/SalesCalculator.php` - Core calculation engine with Excel formula mapping
- `app/Helpers/FieldMapper.php` - Maps various field naming conventions
- `app/Application/Services/SaleService.php` - Main business logic for sales operations
- `routes/api.php` - API route definitions (~38KB - extensive API surface)
- `routes/web.php` - Web route definitions (~129KB - includes Filament routes)
- `vite.config.js` - Frontend build with code splitting and optimization
- `playwright.config.js` - E2E test configuration (90s timeout, sequential execution, single worker)
- `phpstan.neon` - Static analysis rules (level 5)
- `pint.json` - Laravel Pint code formatting rules
- `phpunit.xml` - PHPUnit configuration (SQLite in-memory for testing)
- `composer.json` - Composer scripts: dev, dev-with-logs, test, analyse, format, quality

## Git Workflow & Branching Strategy

This project follows **GitHub Flow** optimized for **1-person development with AI assistants** (Claude Code + Cursor).

### ⚠️ IMPORTANT: Branching Strategy Clarification

**Active Strategy**: AI-Optimized GitHub Flow (documented in this section below)

**Note**: The repository also contains `BRANCHING_STRATEGY.md` which describes traditional Git Flow (main/staging/develop). **That document is legacy documentation**. For current development with Claude Code and Cursor AI, **follow the AI-specific branching strategy in this CLAUDE.md file** (`claude/*`, `cursor/*`, `feature/*` branches from `main`).

**Why AI-Specific GitHub Flow?**
- ✅ Clearer attribution (know which AI tool made which changes)
- ✅ Prevents merge conflicts between AI assistants
- ✅ Simpler workflow for solo developer + AI collaboration
- ✅ No need for long-lived `develop`/`staging` branches
- ✅ Faster iterations and deployments

If you need to reference traditional Git Flow for some reason, see `BRANCHING_STRATEGY.md`, but be aware it's not optimized for AI-assisted development.

### Branch Structure

```
main (production, always deployable)
  ├── claude/* (Claude Code 전용 브랜치)
  │   ├── claude/sales-feature
  │   ├── claude/dashboard-optimization
  │   └── claude/fix-calculation
  ├── cursor/* (Cursor AI 전용 브랜치)
  │   ├── cursor/ui-improvements
  │   ├── cursor/refactor-components
  │   └── cursor/add-feature
  └── feature/* (Manual work or experiments)
      ├── feature/manual-hotfix
      └── feature/experimental
```

### Why AI-Specific Branches?

**Problem**: Claude Code와 Cursor가 같은 브랜치에서 작업하면 충돌 가능성↑
**Solution**: 각 AI 도구가 전용 브랜치에서 작업하여 **작업 히스토리 명확화**

**Benefits**:
- ✅ AI 도구 간 충돌 방지
- ✅ 작업 주체 명확 (커밋 히스토리로 추적 가능)
- ✅ AI별 코드 스타일 일관성 유지
- ✅ 롤백 시 영향 범위 파악 용이

### Branch Naming Convention

**Format**: `<tool>/<domain>-<description>`

#### Claude Code Branches (claude/*)
```bash
# Sales Management (판매 관리)
claude/sales-bulk-import-v2
claude/sales-calculation-refactor
claude/fix-sales-precision

# Dashboard & Statistics (대시보드/통계)
claude/dashboard-chart-selector
claude/dashboard-optimization
claude/perf-dashboard-cache

# Store & Branch Management (매장/지사 관리)
claude/store-bulk-upload
claude/store-account-mgmt
claude/fix-store-rbac

# Calculation & Settlement (계산/정산)
claude/calculation-optimization
claude/settlement-automation
claude/fix-dealer-profile

# Backend/API Work (백엔드/API)
claude/api-endpoint-refactor
claude/database-migration
claude/security-enhancement
```

#### Cursor Branches (cursor/*)
```bash
# UI/UX Improvements (UI/UX 개선)
cursor/ui-responsive-design
cursor/ui-dark-mode
cursor/component-library

# Frontend Features (프론트엔드 기능)
cursor/chart-improvements
cursor/form-validation
cursor/table-virtualization

# Styling & Layout (스타일링/레이아웃)
cursor/tailwind-refactor
cursor/mobile-layout
cursor/accessibility

# Component Refactoring (컴포넌트 리팩토링)
cursor/refactor-hooks
cursor/optimize-renders
cursor/split-components
```

#### Manual/Experimental Branches (feature/*)
```bash
# Emergency fixes (긴급 수정)
feature/hotfix-critical-bug
feature/emergency-deploy

# Experiments (실험적 기능)
feature/experimental-ai-feature
feature/poc-new-architecture
```

### Workflow: Claude Code Development

```bash
# 1. Create Claude branch from main
git checkout main
git pull origin main
git checkout -b claude/sales-bulk-import

# 2. Let Claude Code do the work with multiple commits
# Claude will automatically commit with proper messages
git add .
git commit -m "🤖 feat(sales): add CSV parser for bulk import"
git commit -m "🤖 feat(sales): add validation for imported data"
git commit -m "🤖 test(sales): add unit tests for CSV parser"

# 3. Push and create Pull Request
git push origin claude/sales-bulk-import
# Create PR on GitHub: claude/sales-bulk-import → main

# 4. Request AI review (optional but recommended)
# Ask Claude Code: "Please review the code in this PR"
# Or use: /sc:analyze for automated review

# 5. After review, merge to main
# No approval required (1-person dev), but CI must pass
# Squash merge recommended for clean history

# 6. Delete branch after merge
git branch -d claude/sales-bulk-import
git push origin --delete claude/sales-bulk-import
```

### Workflow: Cursor Development

```bash
# 1. Create Cursor branch from main
git checkout main
git pull origin main
git checkout -b cursor/ui-improvements

# 2. Use Cursor AI for development
# Cursor Composer or Chat features
# Commits with Cursor tag
git add .
git commit -m "🔮 feat(ui): improve responsive layout"
git commit -m "🔮 style(ui): refactor Tailwind classes"

# 3. Push and create Pull Request
git push origin cursor/ui-improvements
# Create PR: cursor/ui-improvements → main

# 4. Review in Claude Code (cross-check)
# Switch to Claude Code and ask:
# "Please review the code in cursor/ui-improvements branch"

# 5. Merge to main after CI passes
# Delete branch
git branch -d cursor/ui-improvements
git push origin --delete cursor/ui-improvements
```

### Workflow: Emergency Hotfix

```bash
# 1. Create hotfix branch from main (use feature/* for clarity)
git checkout main
git checkout -b feature/hotfix-critical-bug

# 2. Fix immediately (manual or with AI)
git commit -m "🚨 hotfix: fix critical sales calculation bug"
composer test  # Must pass!

# 3. Deploy ASAP - Direct merge to main
git checkout main
git merge --no-ff feature/hotfix-critical-bug
git tag -a v1.3.1 -m "Hotfix v1.3.1 - Critical bug fix"
git push origin main --tags

# 4. Delete hotfix branch
git branch -d feature/hotfix-critical-bug
```

### AI Code Review Process

#### How to Request Claude Code Review

```bash
# After pushing your branch
git push origin claude/your-feature

# In Claude Code chat:
"Please review the code in claude/your-feature branch:
- Check for potential bugs
- Verify test coverage
- Suggest improvements
- Check security issues"

# Or use slash command:
/sc:analyze
```

#### How to Request Cursor Review

```bash
# In Cursor IDE
1. Open the PR in Cursor
2. Use Cursor Chat: "Review this PR for code quality"
3. Or use Cursor Composer for inline suggestions
```

#### Cross-Review (Recommended)

**Best Practice**: Ask the other AI to review
- Claude branch → Ask Cursor to review
- Cursor branch → Ask Claude to review
- Different perspectives = Better code quality

### Branch Protection Rules (1-Person Dev Optimized)

**main branch:**
- ✅ Require pull requests (for history tracking)
- ⬜ **NO approval required** (1-person dev)
- ✅ Require status checks to pass (CI/CD tests)
- ⬜ Require branches to be up to date (optional, for flexibility)
- ❌ Allow force pushes (disabled for safety)
- ✅ Auto-merge after CI passes (optional, for speed)

**No develop branch needed** - GitHub Flow uses main only

### Branch Lifecycle Rules

1. **AI branches (claude/*, cursor/*)**: Maximum lifetime 3 days
   - Short-lived branches for focused changes
   - Merge quickly to avoid drift from main
   - Delete immediately after merge

2. **Manual branches (feature/*)**: Maximum lifetime 1 week
   - Break into smaller tasks if longer
   - Merge or close stale branches

3. **Hotfix branches**: Maximum lifetime 4 hours
   - Emergency only
   - Immediate merge + deploy

### Commit Message Convention with AI Tags

Follow conventional commits with AI tool identification:

```bash
# Format: <emoji> <type>(<scope>): <subject>

# Claude Code commits (use 🤖 emoji)
🤖 feat(sales): add bulk import via CSV
🤖 fix(dashboard): correct chart date range calculation
🤖 perf(api): optimize sales query with proper indexing
🤖 refactor(auth): extract RBAC logic to service
🤖 test(calculation): add unit tests for dealer profiles

# Cursor commits (use 🔮 emoji)
🔮 feat(ui): add responsive layout
🔮 style(components): refactor Tailwind classes
🔮 fix(ui): correct mobile menu positioning
🔮 refactor(hooks): extract custom hooks

# Manual commits (use standard emojis)
✨ feat(auth): implement 2FA
🐛 fix(critical): patch security vulnerability
🚨 hotfix: emergency production fix
📝 docs(readme): update installation guide
🔧 chore(deps): upgrade Laravel to 12.0
```

**Commit Types:**
- `feat`: New feature
- `fix`: Bug fix
- `perf`: Performance improvement
- `refactor`: Code refactoring (no functional change)
- `test`: Adding/updating tests
- `docs`: Documentation only
- `style`: Code style/formatting (no logic change)
- `chore`: Maintenance tasks
- `security`: Security fixes

**AI Tool Emojis:**
- 🤖 = Claude Code
- 🔮 = Cursor AI
- ✨ = Manual (new feature)
- 🐛 = Manual (bug fix)
- 🚨 = Emergency/Hotfix

### Module Dependencies & Impact

When working on these modules, be aware of impact:

**High Impact (affects many modules):**
- `SalesCalculator` helper → Sales, Calculation, Dashboard, Settlement
- `RBACMiddleware` → All API endpoints
- `User/Auth` → All modules (authentication required)

**Medium Impact:**
- `Sales` model → Dashboard, Statistics, Settlement, Reports
- `Store/Branch` models → Sales, Dashboard, User management

**Low Impact (isolated modules):**
- `DailyExpense`, `FixedExpense`, `Refund`, `Payroll`
- Can be developed independently

### Domain-Specific Development Notes

#### Sales Module
- **ALWAYS test** with `SalesCalculatorTest` when changing calculation logic
- **Check Excel formulas** match specifications in CLAUDE.md
- **Verify RBAC** for all role types (headquarters/branch/store)

#### Dashboard Module
- **Performance critical**: Check query count and response time
- **Cache properly**: Use 5-minute TTL for statistics
- **Test with real data**: Use seeded database for accurate testing

#### Store/Branch Module
- **Queue testing**: Verify bulk operations trigger jobs correctly
- **Excel validation**: Test with malformed CSV/Excel files
- **User creation**: Ensure account generation works correctly

#### Auth/RBAC Module
- **Security first**: Test all permission boundaries
- **Audit changes**: All auth changes require security review
- **Test all roles**: Verify headquarters, branch, and store access

## Important Coding Guidelines

### File Creation Policy
- ❌ **NEVER create files unless absolutely necessary** for achieving your goal
- ✅ **ALWAYS prefer editing existing files** to creating new ones
- ❌ **NEVER proactively create documentation files** (*.md) or README files
- ✅ **Only create documentation files if explicitly requested** by the user

### Code Modification Approach
When modifying code:
1. Read the existing file first to understand the current implementation
2. Make targeted edits to existing files rather than creating new ones
3. Maintain consistency with existing code style and patterns
4. Follow the project's established architectural patterns

---

## Quick Reference Card

### 🚀 Daily Development Workflow

```bash
# Start development (pick one)
composer dev                    # Full stack (Laravel + Queue + Vite) - recommended
simple-start.bat               # Just Laravel (fastest for backend work)
npm run dev                    # Just Vite (frontend only)

# Local URLs
http://localhost:8000          # Laravel application
http://localhost:5173          # Vite dev server (HMR)
```

### 🧪 Testing Commands

```bash
# Backend tests
composer test                  # Run all PHPUnit tests
composer quality               # Full quality check (format + analyse + test)
./vendor/bin/phpunit --filter="TestClassName"  # Specific test

# Frontend tests
npm run test:smoke             # Quick E2E smoke tests (~2 min)
npx playwright test --headed   # E2E with browser UI
npx playwright test --grep="rbac"  # Specific test category

# Code quality
composer format                # Auto-fix code style (Laravel Pint)
composer analyse               # Run PHPStan (level: max)
```

### 🗄️ Database Operations

```bash
# CRITICAL: Always backup before migrations!
cd backups && backup_supabase.bat YOUR_PROJECT_ID

# Migrations (NEVER use migrate:fresh in production!)
php artisan migrate            # Run pending migrations only
php artisan migrate:rollback   # Rollback last batch
php artisan migrate:status     # Check migration status

# Local PostgreSQL
postgresql-17.6-2-windows-x64-binaries/bin/pg_ctl.exe -D postgresql-data start
postgresql-17.6-2-windows-x64-binaries/bin/psql.exe -U postgres -d ykp_dashboard_local
```

### 🌿 Git Workflow (AI-Specific Branching)

```bash
# Claude Code workflow
git checkout main
git pull origin main
git checkout -b claude/feature-name
# ... make changes ...
git commit -m "🤖 feat(sales): add feature description"
git push origin claude/feature-name
# Create PR → main

# Cursor AI workflow
git checkout -b cursor/ui-improvement
git commit -m "🔮 style(ui): improve layout"

# Emergency hotfix
git checkout -b feature/hotfix-critical-bug
git commit -m "🚨 hotfix: fix critical bug"
```

### 🔑 Test Accounts (Local Development)

```
Store:        store@ykp.com   / password
Branch:       branch@ykp.com  / password
Headquarters: admin@ykp.com   / password
```

### 📊 Key Calculation Formulas

```
Total Rebate (T) = K + L + M + N + O
Settlement (U)   = T - P + Q + R - S + W - X
Tax (V)          = 0  [Currently disabled]
Margin Before (Y) = U  [Currently = Settlement]
Margin After (Z)  = U  [Currently = Settlement]

⚠️ NOTE: Deduction (S) is SUBTRACTED, not added!
```

### 🔍 Common Debugging

```bash
# View Laravel logs
tail -f storage/logs/laravel.log

# Queue jobs
php artisan queue:listen       # Watch queue worker
php artisan queue:failed       # View failed jobs

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Database queries
php artisan tinker
>>> DB::enableQueryLog();
>>> // ... run query ...
>>> dd(DB::getQueryLog());
```

### 🚨 Critical Reminders

1. **ALWAYS backup Supabase before migrations**: `backup_supabase.bat`
2. **NEVER use `migrate:fresh` in production** - data will be lost!
3. **Run `composer quality` before commits** - ensure code quality
4. **Use AI-specific branches**: `claude/*` or `cursor/*`
5. **Tax calculation is disabled** - Settlement = Margin currently
6. **SSL required** for Supabase production connection
7. **Environment separation**: Local uses `.env` (Git-ignored), Production uses Railway variables

### 📂 Key Files to Check

```
app/Helpers/SalesCalculator.php       # Core calculation logic
config/features.php                   # Feature flags
config/sales.php                      # Business rules
routes/api.php                        # API endpoints
.github/workflows/ci.yml              # CI/CD pipeline
phpstan.neon                          # Static analysis config
```

### 📚 Documentation References

- **Laravel 12**: https://laravel.com/docs/12.x
- **Filament v4**: https://filamentphp.com/docs
- **React 18**: https://react.dev/
- **Playwright**: https://playwright.dev/
- **TailwindCSS v4**: https://tailwindcss.com/docs

---

**Last Updated**: 2025-11-04 | **Project**: YKP ERP Dashboard | **Stack**: Laravel 12 + React 18