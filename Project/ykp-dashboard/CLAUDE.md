# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the YKP ERP Dashboard - a Laravel-based web application with React frontend components for managing sales, stores, and business operations. The system uses PostgreSQL (production) with SQLite fallback for testing, implements role-based access control (RBAC), and includes comprehensive Filament admin panels.

## Architecture

### Backend Stack
- **Laravel 12** with PHP 8.2+
- **Filament v4** for admin interfaces
- **PostgreSQL** (production) / **SQLite** (testing)
- **Application Service Pattern** with dedicated service classes in `app/Application/Services/`

### Frontend Stack
- **Vite** build system with React integration
- **React 18** with modern hooks and context
- **TailwindCSS v4** for styling
- **AG-Grid** for advanced data tables
- **Chart.js** for data visualization
- **TanStack Query** for API state management

### Key Architectural Patterns
- **Domain-Driven Design**: Business logic separated into Application Services
- **RBAC System**: Role-based access control with middleware
- **API-First Design**: RESTful APIs with consistent response structures
- **Feature Flags**: Environment-based feature toggling
- **Job Queue System**: Background processing for heavy calculations

## Development Commands

### Laravel Commands
```bash
# Start development server with all services
composer dev                    # Runs PHP server + queue + Vite concurrently
composer dev-with-logs         # Same as above + Laravel Pail logs

# Testing
composer test                  # Run PHPUnit tests
composer quality               # Run full quality suite (format + analyze + test)
./vendor/bin/phpunit --filter="SaleStatisticsTest"  # Single test class

# Code Quality
composer format                # Run Laravel Pint (code formatter)  
composer format-check          # Check formatting without changes
composer analyse               # Run PHPStatan static analysis
./vendor/bin/phpstan analyse --level=max  # Full static analysis

# Database
php artisan migrate:fresh      # Fresh migration (development only)
php artisan db:seed            # Run database seeders
```

### Frontend Commands  
```bash
# Development
npm run dev                    # Start Vite dev server
npm run build                  # Production build
npm run preview                # Preview production build

# Testing
npm run test:smoke             # Playwright smoke tests
npx playwright test            # Full Playwright test suite
```

### Integrated Development
```bash
# Full development environment
composer dev                   # Best option - runs everything needed

# Production deployment preparation  
npm run predeploy              # Build + cache config/routes/views
```

## Database Schema

### Core Entities
- **Users**: RBAC system (headquarters/branch/store roles)
- **Branches**: Regional management units
- **Stores**: Individual retail locations
- **Sales**: Transaction records with automatic calculations
- **MonthlySettlements**: Month-end financial reconciliation
- **DealerProfiles**: Calculation configuration per dealer

### Key Relationships
- Users belong to Branches and/or Stores
- Sales are tied to Stores and calculated using DealerProfiles  
- MonthlySettlements aggregate Sales data by period

## API Architecture

### Authentication & Authorization
- **Session-based auth** for web routes (`/api/` prefix)
- **RBAC middleware** enforces role-based access
- **CSRF protection** for state-changing operations
- **Rate limiting** on calculation endpoints

### API Patterns
```php
// Standard response format
['success' => bool, 'data' => mixed, 'meta' => optional]

// Error responses  
['success' => false, 'error' => string, 'code' => optional]
```

### Key Endpoints
- `GET /api/dashboard/overview` - Main dashboard statistics
- `POST /api/sales/bulk-save` - Bulk sales data import
- `POST /api/calculation/profile/row` - Real-time margin calculations
- `GET /api/monthly-settlements` - Financial reconciliation data

## Testing Strategy

### PHPUnit Tests (`tests/`)
- **Unit Tests**: `tests/Unit/` - Business logic and calculations
- **Feature Tests**: `tests/Feature/` - API endpoints and integration
- **Focus Areas**: SalesCalculator, API responses, user permissions

### Playwright Tests (`tests/playwright/`)
- **E2E Workflows**: Complete user journeys
- **Cross-Role Testing**: Headquarters vs Branch vs Store access
- **UI Components**: Form interactions, data grids, charts
- **Performance**: Load testing for calculation APIs

### Running Tests
```bash
# Backend tests
./vendor/bin/phpunit
./vendor/bin/phpunit --filter="SalesCalculatorTest"

# Frontend E2E tests  
npx playwright test
npx playwright test --headed    # With browser UI
```

## Development Workflows

### Sales Data Management
Sales are the core entity. The system supports:
- **Excel-like bulk input** via AG-Grid
- **Real-time calculation** using DealerProfile configurations
- **Background job processing** for large datasets
- **Automatic settlement generation** for month-end reconciliation

### Calculation Engine
Located in `app/Helpers/SalesCalculator.php`:
- **Real-time margin calculations** based on dealer profiles
- **Multiple calculation strategies** per dealer type
- **Performance optimized** for bulk operations
- **Validation and error handling** for edge cases

### Role-Based Features
- **Headquarters**: Full system access, user management, cross-branch reporting
- **Branch**: Branch-specific data, store management within branch
- **Store**: Store-specific sales input and basic reporting

## File Structure Notes

### Key Directories
- `app/Application/Services/` - Business logic services
- `app/Helpers/` - Utility classes (especially SalesCalculator)
- `resources/js/components/` - React components
- `resources/js/hooks/` - Custom React hooks
- `resources/views/` - Blade templates (mixed with React)

### Configuration Files
- `vite.config.js` - Frontend build configuration with chunk splitting
- `phpunit.xml` - Test configuration with SQLite in-memory DB
- `playwright.config.js` - E2E test configuration
- `pint.json` - Code formatting rules
- `phpstan.neon` - Static analysis configuration

## Environment Setup

### Required Environment Variables
```env
DB_CONNECTION=pgsql              # PostgreSQL for production
FEATURE_EXCEL_INPUT=true         # Enable Excel-like input features
FEATURE_ADVANCED_REPORTS=true    # Enable advanced reporting
```

### Feature Flags
The system uses feature flags for progressive rollouts:
- `FEATURE_UI_V2` - New UI components
- `FEATURE_SUPABASE_ENHANCED` - Enhanced database features
- `FEATURE_EXCEL_INPUT` - Excel-style bulk input

## Performance Considerations

### Database
- **Indexes** on frequently queried columns (store_id, sale_date, agency)  
- **Aggregate queries** for statistics to avoid N+1 problems
- **Connection pooling** in production environment

### Frontend
- **Code splitting** configured in Vite for optimal bundle sizes
- **Virtual scrolling** for large data tables  
- **Query caching** with TanStack Query
- **Image optimization** with lazy loading

### API
- **Rate limiting** on calculation endpoints
- **Background jobs** for heavy processing
- **Response caching** for dashboard statistics