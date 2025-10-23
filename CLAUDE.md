# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

YKP ERP Dashboard - A Laravel-based sales management system with React frontend, implementing role-based access control (RBAC) for headquarters, branches, and stores. The system features real-time sales calculations, bulk data entry via Excel-like interfaces, and comprehensive financial reporting.

## Architecture

### Backend Stack
- **Laravel 12** with PHP 8.2+
- **Filament v4** for admin panels
- **PostgreSQL** (production) / **SQLite** (testing)
- **Application Service Pattern** in `app/Application/Services/`
- **Job Queue System** for background processing

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
- **Background Job Queue**: Asynchronous processing for bulk operations
- **Excel Export**: Laravel Excel integration for data export

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
composer analyse               # Run PHPStan static analysis
./vendor/bin/phpstan analyse --level=max app/Helpers/SalesCalculator.php  # Analyze specific file
./vendor/bin/pint app/         # Format specific directory
```

### Database Management
```bash
php artisan migrate:fresh      # Reset database (dev only)
php artisan migrate:fresh --seed  # Reset with seeders
php artisan db:seed            # Run seeders only
php artisan migrate            # Run pending migrations
php artisan migrate:rollback   # Rollback last migration batch
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
- **users**: System users with role-based access
- **branches**: Regional management units
- **stores**: Individual retail locations
- **sales**: Transaction records with calculated margins
- **monthly_settlements**: Month-end financial reconciliation
- **dealer_profiles**: Calculation configurations per dealer

### Key Relationships
```
Users → Branches (many-to-one)
Users → Stores (many-to-one)
Stores → Branches (many-to-one)
Sales → Stores (many-to-one)
Sales → DealerProfiles (for calculations)
MonthlySettlements → Sales (aggregates by period)
```

## API Architecture

### Authentication
- Session-based authentication for web routes
- CSRF protection enabled for state-changing operations
- RBAC middleware enforces role-based access

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
```

### Middleware Layer
The application uses several key middleware for security and functionality:

**RBAC Middleware** (`app/Http/Middleware/RBACMiddleware.php`)
- Enforces role-based access control across all routes
- Validates user permissions based on headquarters/branch/store roles
- Automatically filters data based on user's organizational scope

**API Authentication** (`app/Http/Middleware/ApiAuthenticate.php`)
- Session-based authentication for API routes
- Handles unauthenticated requests with JSON responses
- Integrates with Laravel's authentication system

**Performance Monitoring** (`app/Http/Middleware/PerformanceMonitoringMiddleware.php`)
- Tracks request/response times for API endpoints
- Logs slow queries and performance bottlenecks
- Provides metrics for optimization

**CSRF Protection** (`app/Http/Middleware/VerifyCsrfToken.php`)
- Protects state-changing operations
- Excludes specific API endpoints when needed
- Prevents cross-site request forgery attacks

## Sales Calculation Engine

Located in `app/Helpers/SalesCalculator.php`:
- Implements Excel-like calculation formulas
- Real-time margin calculations based on dealer profiles
- Handles both basic and profile-based calculations
- Tax rate: 10% (constant: TAX_RATE)
- Key calculations: total_rebate, settlement, tax, margin_before, margin_after

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

## Role-Based Features

### Headquarters (role: headquarters)
- Full system access including user management
- Cross-branch reporting and analytics
- Dealer profile management
- System-wide statistics and reports

### Branch (role: branch)
- Branch-specific data access only
- Store management within their branch
- Branch-level reporting and analytics
- Cannot access other branches' data

### Store (role: store)
- Store-specific sales input only
- Basic reporting for their store
- No access to branch or HQ features
- Limited to their own sales data

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
- Code splitting via Vite for optimal chunks
- Virtual scrolling in AG-Grid for large datasets
- TanStack Query caching with 5-minute stale time
- Lazy loading for images and heavy components

### API
- Rate limiting on calculation endpoints (60/minute)
- Background jobs for bulk operations > 100 rows
- Response caching for statistics (5-minute TTL)

## Background Job Queue

The system uses Laravel's queue system for asynchronous processing:

### Job Classes
**ProcessBatchCalculationJob** (`app/Jobs/ProcessBatchCalculationJob.php`)
- Handles bulk sales calculations in background
- Automatically triggered for operations with >100 rows
- Uses dealer profiles for calculation consistency
- Provides progress tracking and error handling
- Retry mechanism: 3 attempts with exponential backoff

### Queue Configuration
```bash
# Start queue worker
php artisan queue:listen --tries=1

# Monitor queue in real-time
php artisan queue:work --verbose

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

### Queue Usage in Code
```php
// Dispatch job for bulk calculation
if (count($rows) > 100) {
    ProcessBatchCalculationJob::dispatch($rows, $profile);
    return response()->json(['status' => 'queued']);
}
```

## Excel Export Features

The system includes Excel export capabilities using Laravel Excel (maatwebsite/excel):

### Export Classes
**StoreStatisticsExport** (`app/Exports/StoreStatisticsExport.php`)
- Exports store-level statistics to Excel format
- Supports filtering by date range, branch, and store
- Includes calculated fields (margins, totals)
- Formatted for business reporting

### Export Endpoints
```
GET /api/sales/export              # Export sales data
GET /api/statistics/export/stores  # Export store statistics
```

### Usage Example
```php
// Export with filters
return Excel::download(
    new StoreStatisticsExport($filters),
    'store-statistics-' . date('Y-m-d') . '.xlsx'
);
```

## Environment Configuration

### Required Environment Variables
```env
# Database
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=ykp_dashboard
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Feature Flags
FEATURE_EXCEL_INPUT=true
FEATURE_ADVANCED_REPORTS=true
FEATURE_UI_V2=false
FEATURE_SUPABASE_ENHANCED=false

# Queue Configuration
QUEUE_CONNECTION=database
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

### Adding Excel Export for New Data Type
1. Create export class in `app/Exports/` extending `FromCollection`
2. Implement `collection()` method with data query
3. Add `headings()` method for column headers
4. Register route in `routes/api.php` with appropriate middleware
5. Add download trigger in frontend component

### Working with Background Jobs
1. Create job class: `php artisan make:job ProcessYourDataJob`
2. Implement `handle()` method with business logic
3. Dispatch job: `ProcessYourDataJob::dispatch($data)`
4. Monitor execution: `php artisan queue:work --verbose`
5. Handle failures: `php artisan queue:failed` and `queue:retry`

## File Structure

### Key Directories
```
app/
├── Application/Services/   # Business logic services
├── Helpers/               # Utilities (SalesCalculator)
├── Http/Controllers/Api/  # API controllers
├── Models/               # Eloquent models
├── Jobs/                 # Background job classes
├── Exports/              # Excel export classes
resources/
├── js/components/        # React components
├── js/hooks/            # Custom React hooks
├── views/               # Blade templates
tests/
├── Unit/                # Unit tests
├── Feature/             # API tests
├── playwright/          # E2E tests
```

### Application Service Layer
The `app/Application/Services/` directory contains domain-specific business logic:

**SaleService.php** (`app/Application/Services/SaleService.php`)
- Core sales data management
- CRUD operations with business rules
- Integration with SalesCalculator
- Role-based data filtering

**MonthlySettlementService.php**
- Month-end financial reconciliation
- Aggregation of sales data by period
- Settlement calculations and reports
- Invoice generation

**PayrollService.php**
- Employee payroll management
- Salary calculations based on sales performance
- Commission tracking and distribution
- Integration with sales metrics

**RefundService.php**
- Refund request processing
- Financial adjustment calculations
- Audit trail for refund transactions
- Integration with settlement system

**ExpenseService.php**
- Daily and fixed expense tracking
- Budget allocation and monitoring
- Expense categorization and reporting
- Integration with monthly settlements

### Service Pattern Usage
```php
// Example: Using SaleService
use App\Application\Services\SaleService;

class SalesApiController extends Controller
{
    public function __construct(private SaleService $saleService) {}

    public function store(CreateSaleRequest $request)
    {
        return $this->saleService->create($request->validated());
    }
}
```

### Important Files
- `app/Helpers/SalesCalculator.php` - Core calculation engine
- `routes/api.php` - API route definitions
- `vite.config.js` - Frontend build configuration
- `playwright.config.js` - E2E test configuration
- `phpstan.neon` - Static analysis rules
- `pint.json` - Code formatting rules

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