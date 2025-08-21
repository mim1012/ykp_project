<?php

namespace App\Providers;

use App\Application\Services\SaleService;
use App\Application\Services\SaleServiceInterface;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Policy 등록
        Gate::policy(User::class, UserPolicy::class);
        
        // 개발 환경에서만 성능 모니터링 활성화
        if (config('app.debug')) {
            $this->enableQueryLogging();
        }
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
