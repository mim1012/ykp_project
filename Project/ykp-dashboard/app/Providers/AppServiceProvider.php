<?php

namespace App\Providers;

use App\Application\Services\SaleService;
use App\Application\Services\SaleServiceInterface;
use App\Application\Services\PayrollService;
use App\Application\Services\ExpenseService;
use App\Application\Services\RefundService;
use App\Services\FeatureService;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Service 바인딩
        $this->app->bind(SaleServiceInterface::class, SaleService::class);
        
        // 새로운 서비스들 등록
        $this->app->singleton(PayrollService::class);
        $this->app->singleton(ExpenseService::class);
        $this->app->singleton(RefundService::class);
        $this->app->singleton(FeatureService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Policy 등록
        Gate::policy(User::class, UserPolicy::class);
        
        // Feature Flag Blade Directives 등록
        $this->registerFeatureFlagDirectives();
        
        // 개발 환경에서만 성능 모니터링 활성화
        if (config('app.debug')) {
            $this->enableQueryLogging();
        }
    }

    /**
     * Feature Flag Blade Directives 등록
     */
    private function registerFeatureFlagDirectives(): void
    {
        // @feature('feature_name') ... @endfeature
        Blade::directive('feature', function ($feature) {
            return "<?php if(app('App\\Services\\FeatureService')->isEnabled({$feature})): ?>";
        });
        
        Blade::directive('endfeature', function () {
            return '<?php endif; ?>';
        });
        
        // @developeronly('feature_name') ... @enddeveloperonly  
        Blade::directive('developeronly', function ($feature) {
            return "<?php if(app('App\\Services\\FeatureService')->isDeveloperOnly({$feature})): ?>";
        });
        
        Blade::directive('enddeveloperonly', function () {
            return '<?php endif; ?>';
        });
    }

    /**
     * 쿼리 로깅 활성화
     */
    private function enableQueryLogging(): void
    {
        \DB::listen(function ($query) {
            if ($query->time > 100) { // 100ms 이상 쿼리만 로깅
                \Log::warning('Slow Query Detected', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time.'ms',
                ]);
            }
        });
    }
}
