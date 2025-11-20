# API Architecture & Core Components

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

**See**: [CALCULATIONS.md](CALCULATIONS.md) for detailed formula documentation

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

## Performance Optimizations

### Database
- Indexes on: store_id, sale_date, agency, branch_id
- Aggregate queries to avoid N+1 problems
- Query result caching for dashboard stats

### API
- Rate limiting on calculation endpoints (60/minute)
- Background jobs for bulk operations > 100 rows
- Response caching for statistics (5-minute TTL)

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

---

**Back to**: [Development Docs Index](README.md) | [CLAUDE.md](../../CLAUDE.md)
