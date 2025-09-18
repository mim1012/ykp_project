<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PerformanceMonitoringMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);
        $startMemory = memory_get_usage(true);

        $response = $next($request);

        $duration = round((microtime(true) - $start) * 1000, 2);
        $memoryUsed = memory_get_peak_usage(true) - $startMemory;

        // 200ms 이상이거나 API 요청인 경우만 로깅
        if ($duration > 200 || $request->is('api/*')) {
            Log::info('api_performance', [
                'method' => $request->method(),
                'path' => $request->path(),
                'duration_ms' => $duration,
                'memory_mb' => round($memoryUsed / 1024 / 1024, 2),
                'user_id' => optional($request->user())->id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString(),
            ]);
        }

        // 응답 헤더에 성능 정보 추가 (개발 환경에서만)
        if (config('app.debug')) {
            $response->headers->set('X-Response-Time', $duration.'ms');
            $response->headers->set('X-Memory-Usage', round($memoryUsed / 1024 / 1024, 2).'MB');
        }

        return $response;
    }
}
