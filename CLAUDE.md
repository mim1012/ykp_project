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

// Calculated fields
total_rebate = K+L+M+N+O     → T (리베총계)
settlement = T-P+Q+R+S       → U (정산금)
tax = U × 0.10               → V (세금)
margin_before = U-V+W+X      → Y (세전마진)
margin_after = V+Y           → Z (세후마진)
```

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
- SalesCalculator computations
- DealerProfile calculations
- User permission checks

### Feature Tests (`tests/Feature/`)
API endpoint testing:
- Authentication and authorization
- RBAC enforcement
- Sales data CRUD operations
- Statistics API responses

### E2E Tests (`tests/playwright/`)
Complete user workflows:
- Role-based login scenarios
- Sales data entry via AG-Grid
- Report generation
- Cross-role data isolation

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

### Required Environment Variables
```env
# Database - Local PostgreSQL
DB_CONNECTION=pgsql_local
DB_HOST_LOCAL=localhost
DB_PORT_LOCAL=5432
DB_DATABASE_LOCAL=ykp_dashboard_local
DB_USERNAME_LOCAL=postgres
DB_PASSWORD_LOCAL=1234

# Database - Production Supabase (optional, for backup/comparison)
DB_CONNECTION_PROD=pgsql
DB_HOST_PROD=aws-1-ap-southeast-1.pooler.supabase.com
DB_PORT_PROD=5432
DB_DATABASE_PROD=postgres
DB_USERNAME_PROD=postgres.qwafwqxdcfpqqwpmphkm
DB_PASSWORD_PROD=your_password
DB_SSLMODE_PROD=require

# Feature Flags
FEATURE_EXCEL_INPUT=true
FEATURE_ADVANCED_REPORTS=true
FEATURE_UI_V2=false
FEATURE_SUPABASE_ENHANCED=true

# Queue Configuration
QUEUE_CONNECTION=database

# Session/Cache
SESSION_DRIVER=database
CACHE_STORE=database
```

### Feature Flags Usage
Check feature availability in code:
```php
if (config('features.excel_input')) {
    // Excel-like input features
}
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