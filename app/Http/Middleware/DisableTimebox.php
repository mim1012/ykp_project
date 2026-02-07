<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Laravel 11 Timebox 오류 해결용 미들웨어
 */
class DisableTimebox
{
    public function handle(Request $request, Closure $next)
    {
        // Timebox 기능 비활성화
        if (config('app.env') === 'production') {
            config(['auth.timebox.enabled' => false]);
        }

        return $next($request);
    }
}
