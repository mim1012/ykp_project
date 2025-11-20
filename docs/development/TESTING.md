# Testing Strategy

## Overview

The project uses a comprehensive 3-tier testing strategy:
- **Unit Tests**: Business logic and calculations (PHPUnit)
- **Feature Tests**: API endpoints and database integration (PHPUnit + Pest)
- **E2E Tests**: Complete user workflows (Playwright)

## Unit Tests (`tests/Unit/`)

Focus on business logic, especially:
- `SalesCalculatorTest.php` - Core calculation engine (formulas, margins, taxes)
- `UserPermissionTest.php` - RBAC permission validation
- `DashboardControllerTest.php` - Dashboard statistics logic
- `DeductionCalculationTest.php` - Deduction formula edge cases

### Running Unit Tests

```bash
./vendor/bin/phpunit --testsuite=Unit
./vendor/bin/phpunit tests/Unit/SalesCalculatorTest.php  # Single test file
```

## Feature Tests (`tests/Feature/`)

API endpoint testing with database:
- `SaleBulkCreateTest.php.pest` - Bulk sales creation API (Pest PHP)
- `SaleStatisticsTest.php.pest` - Statistics aggregation API (Pest PHP)
- `StatisticsApiTest.php` - Statistics endpoint responses
- `CalculationApiPerformanceTest.php` - Performance benchmarks for calculations
- `ChangePasswordTest.php` - Password change E2E flow

### Running Feature Tests

```bash
./vendor/bin/phpunit --testsuite=Feature
./vendor/bin/pest                                        # Run all Pest tests
./vendor/bin/pest tests/Feature/SaleBulkCreateTest.php  # Single Pest test
```

## E2E Tests (`tests/playwright/`)

**Total**: 100+ Playwright spec files covering complete workflows

### Smoke Tests (Tagged with `@smoke`)

Critical post-deployment checks:
- `deploy-smoke.spec.js` - Basic app functionality verification

**Run Smoke Tests**:
```bash
npm run test:smoke                           # Quick smoke test suite
npx playwright test --grep=@smoke            # Alternative
```

### RBAC Tests

Role-based access control enforcement:
- `comprehensive-rbac-test.spec.js` - Full RBAC matrix testing
- `role-based-access-test.spec.js` - Role-specific feature access
- `e2e-rbac-branch-restriction.spec.js` - Branch data isolation

**Run RBAC Tests**:
```bash
npx playwright test --grep="rbac"
npx playwright test tests/playwright/comprehensive-rbac-test.spec.js
```

### Feature Tests

User workflow testing:
- `e2e-excel-bulk-paste.spec.js` - Excel paste functionality
- `e2e-password-change.spec.js` - Password change flow
- `e2e-sales-save-and-stats.spec.js` - Sales entry + statistics update

**Run Feature Tests**:
```bash
npx playwright test --grep="e2e-"
npx playwright test tests/playwright/e2e-excel-bulk-paste.spec.js
```

### Performance Tests

Load and performance validation:
- `performance-large-dataset.spec.js` - Large data rendering
- `performance-security-test.spec.js` - Security + performance checks

**Run Performance Tests**:
```bash
npx playwright test --grep="performance"
```

### Accessibility Tests

A11y and responsive design:
- `accessibility-keyboard-nav.spec.js` - Keyboard navigation
- `mobile-responsive.spec.js` - Mobile layout validation

**Run Accessibility Tests**:
```bash
npx playwright test --grep="accessibility|mobile"
```

## Test Configuration

### PHPUnit (`phpunit.xml`)
- Database: SQLite in-memory (fast, isolated)
- Environment: Testing environment (`.env.testing`)
- Coverage: Clover format for CI/CD

### Playwright (`playwright.config.js`)
- Timeout: 90 seconds per test
- Workers: 1 (sequential execution for consistency)
- Browsers: Chromium (default)
- Base URL: `http://localhost:8000`
- Screenshots: On failure only
- Video: On first retry

## Running All Tests

```bash
composer quality              # Format + Analyse + Unit + Feature tests
npm run test:smoke            # E2E smoke tests only
npx playwright test           # All E2E tests (100+ specs, ~30-60 min)
```

## Test Best Practices

### Unit Tests
- Test one thing at a time
- Mock external dependencies
- Use descriptive test names
- Cover edge cases and error conditions

### Feature Tests
- Test API contracts, not implementation
- Use factories for test data
- Test RBAC enforcement for all endpoints
- Verify response formats

### E2E Tests
- Test real user workflows
- Use test accounts (store@ykp.com, branch@ykp.com, admin@ykp.com)
- Clean up test data after runs
- Tag critical tests with `@smoke`

## Test Accounts (Local Development)

```
Store:        store@ykp.com   / password
Branch:       branch@ykp.com  / password
Headquarters: admin@ykp.com   / password
```

## CI/CD Integration

Tests run automatically in GitHub Actions on:
- Push to: `main`, `staging`, `develop`
- Pull requests to: `main`, `staging`

**Pipeline includes**:
- Code formatting check (Laravel Pint)
- Static analysis (PHPStan level 5)
- Unit + Feature tests with coverage
- E2E smoke tests
- Coverage upload to Codecov

## Debugging Failed Tests

### PHPUnit Tests
```bash
# Run specific test with verbose output
./vendor/bin/phpunit --filter="testMethodName" --verbose

# Enable SQL query logging
# Add to .env.testing: DB_LOG_QUERIES=true

# Check Laravel logs
tail -f storage/logs/laravel.log
```

### Playwright Tests
```bash
# Run with headed browser (see what's happening)
npx playwright test --headed

# Run specific test file
npx playwright test tests/playwright/specific-test.spec.js

# Debug mode (pause execution)
npx playwright test --debug

# View test report
npx playwright show-report
```

## Performance Testing

### Backend Performance
- Target: API response < 200ms
- Calculation API: < 100ms per row
- Bulk operations: Queue if > 100 rows

### Frontend Performance
- Initial load: < 2s
- Time to interactive: < 3s
- AG-Grid rendering: 1000+ rows smoothly

## Coverage Goals

- **Unit Tests**: 80%+ coverage for business logic
- **Feature Tests**: All API endpoints tested
- **E2E Tests**: All critical user workflows covered

---

**Back to**: [Development Docs Index](README.md) | [CLAUDE.md](../../CLAUDE.md)
