# Monitoring & Debugging Guide

## 📊 Overview

YKP Dashboard implements comprehensive monitoring and debugging tools to ensure system reliability and performance.

## 🛠️ Monitoring Stack

### 1. Sentry Error Tracking
Real-time error monitoring and performance tracking for production issues.

#### Configuration
```env
# .env
SENTRY_LARAVEL_DSN=your-sentry-dsn-here
SENTRY_TRACES_SAMPLE_RATE=0.1  # 10% of transactions
SENTRY_PROFILES_SAMPLE_RATE=0.5  # 50% profiling
SENTRY_SEND_DEFAULT_PII=false  # Privacy protection
```

#### Features
- **Automatic Exception Capture**: All unhandled exceptions are logged
- **Performance Monitoring**: Transaction and query performance tracking
- **User Context**: Captures user role, branch, and store for debugging
- **Breadcrumbs**: Automatic trail of events leading to errors
- **Release Tracking**: Automatic git tag-based versioning

#### Test Integration
```bash
# Verify Sentry is working
php artisan sentry:test
```

#### Privacy & Security
- Sensitive data (passwords, credit cards) automatically filtered
- PII disabled by default
- Headers (authorization, cookies) removed from logs

### 2. Laravel Telescope
Local debugging and inspection tool for development and staging.

#### Configuration
```env
# .env (Development)
TELESCOPE_ENABLED=true
TELESCOPE_PATH=telescope

# .env (Production - Restricted)
TELESCOPE_ENABLED=false  # Or true with strict authorization
```

#### Access Control
- **Local Environment**: Open access
- **Production**: Headquarters users only
- **Custom Authorization**: Add emails in `TelescopeServiceProvider`

#### Features
- **Request Inspector**: All HTTP requests and responses
- **Database Queries**: SQL queries with execution time
- **Jobs & Queues**: Background job monitoring
- **Exceptions**: Detailed exception traces
- **Cache Operations**: Cache hits/misses
- **Mail**: Outgoing email preview
- **Notifications**: All sent notifications
- **Schedule**: Scheduled task execution

#### Accessing Telescope
```
http://your-domain.com/telescope
```

### 3. Application Logs

#### Log Channels
```php
// config/logging.php
'channels' => [
    'stack' => ['single', 'sentry'],
    'single' => ['path' => storage_path('logs/laravel.log')],
    'sentry' => ['driver' => 'sentry'],
]
```

#### Log Levels
- **Production**: `error` (only errors and critical)
- **Staging**: `warning` (warnings and above)
- **Development**: `debug` (everything)

#### Viewing Logs
```bash
# Real-time log monitoring
php artisan pail

# With filters
php artisan pail --filter="ERROR"
php artisan pail --filter="SalesCalculator"

# Specific log file
tail -f storage/logs/laravel.log
```

## 📈 Performance Monitoring

### Database Query Monitoring
Telescope automatically captures slow queries (>100ms) even in production.

```php
// Captured in production
- Slow queries > 100ms
- Failed queries
- N+1 query problems
```

### API Performance
```php
// PerformanceMonitoringMiddleware tracks:
- Request duration
- Memory usage
- Database query count
- Response size
```

### Queue Performance
```php
// Monitored metrics:
- Job processing time
- Failed jobs
- Retry attempts
- Queue size
```

## 🚨 Alert Configuration

### Sentry Alerts
1. **Error Rate Alert**: > 10 errors/minute
2. **Performance Alert**: P95 response time > 3s
3. **Failed Job Alert**: > 5 failures/hour
4. **Database Alert**: Query time > 1s

### Setting Up Alerts
1. Log into Sentry Dashboard
2. Navigate to Alerts → Create Alert Rule
3. Configure thresholds and notifications

## 🐛 Debugging Workflows

### 1. Production Error Investigation
```bash
# 1. Check Sentry for error details
# 2. Reproduce in staging if possible
# 3. Enable Telescope temporarily (if authorized)
# 4. Check detailed logs
php artisan pail --filter="ERROR"

# 5. Run diagnostics
php artisan db:show
php artisan queue:monitor
```

### 2. Performance Issues
```bash
# 1. Check Telescope for slow queries
# 2. Analyze query patterns
php artisan db:monitor

# 3. Check cache hit rates
php artisan cache:monitor

# 4. Profile with Sentry
# Increase SENTRY_TRACES_SAMPLE_RATE temporarily
```

### 3. Sales Calculation Issues
```bash
# 1. Enable debug mode for calculator
LOG_LEVEL=debug php artisan calculate:test

# 2. Check calculation logs
php artisan pail --filter="SalesCalculator"

# 3. Verify dealer profiles
php artisan tinker
>>> \App\Models\DealerProfile::where('is_active', true)->get()
```

## 📊 Monitoring Dashboard URLs

### Production
- **Application**: https://ykpproject-production.up.railway.app
- **Sentry**: https://sentry.io/organizations/your-org/projects/ykp-dashboard
- **Telescope**: https://ykpproject-production.up.railway.app/telescope (HQ only)

### Staging
- **Application**: https://ykp-staging.railway.app
- **Telescope**: https://ykp-staging.railway.app/telescope

## 🔧 Common Issues & Solutions

### High Memory Usage
```bash
# Check memory consumers
php artisan memory:report

# Clear caches
php artisan cache:clear
php artisan view:clear

# Optimize autoloader
composer dump-autoload -o
```

### Slow Response Times
```bash
# Enable query logging
DB_LOG_QUERIES=true

# Check slow queries
grep "slow query" storage/logs/laravel.log

# Analyze indexes
php artisan db:table sales --indexes
```

### Queue Processing Issues
```bash
# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Monitor queue size
php artisan queue:monitor
```

## 🚀 Deployment Monitoring

### Pre-deployment Checklist
```bash
# 1. Run tests
composer test

# 2. Check code quality
composer quality

# 3. Verify migrations
php artisan migrate:status

# 4. Test Sentry
php artisan sentry:test
```

### Post-deployment Verification
```bash
# 1. Check application health
curl https://your-domain.com/up

# 2. Verify Sentry is receiving events
php artisan sentry:test

# 3. Monitor error rate (first 30 minutes)
# Check Sentry dashboard

# 4. Verify critical paths
# - Login flow
# - Sales calculation
# - Report generation
```

## 📝 Logging Best Practices

### Structured Logging
```php
// Good
Log::info('Sales calculation completed', [
    'user_id' => $userId,
    'store_id' => $storeId,
    'row_count' => $count,
    'duration_ms' => $duration,
]);

// Bad
Log::info("Calculated $count rows for user $userId");
```

### Context Preservation
```php
// Add context for entire request
Log::withContext([
    'user_id' => auth()->id(),
    'store_id' => auth()->user()->store_id,
]);
```

### Error Handling
```php
try {
    $result = $this->calculateSales($data);
} catch (\Exception $e) {
    // Log with context
    Log::error('Sales calculation failed', [
        'error' => $e->getMessage(),
        'data' => $data,
        'trace' => $e->getTraceAsString(),
    ]);

    // Report to Sentry
    report($e);

    // Handle gracefully
    throw new CalculationException('계산 중 오류가 발생했습니다');
}
```

## 🔐 Security Considerations

### Sensitive Data
- Never log passwords, tokens, or credit card numbers
- Use `Log::channel('secure')` for audit logs
- Implement data masking for PII

### Access Control
- Telescope requires authentication in production
- Sentry access through organization roles
- Log files protected by server permissions

### Compliance
- Logs retained for 30 days (configurable)
- GDPR compliance through PII filtering
- Audit trail for regulatory requirements

## 📚 Additional Resources

- [Sentry Documentation](https://docs.sentry.io/platforms/php/guides/laravel/)
- [Telescope Documentation](https://laravel.com/docs/telescope)
- [Laravel Logging](https://laravel.com/docs/logging)
- [Performance Best Practices](https://laravel.com/docs/performance)

## 🆘 Emergency Contacts

For critical production issues:
1. Check Sentry dashboard
2. Review Telescope data (if authorized)
3. Contact DevOps team
4. Escalate to senior developers if needed

---

**Last Updated**: 2025-09-18
**Version**: 1.0.0