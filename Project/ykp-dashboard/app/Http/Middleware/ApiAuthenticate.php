<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // API 경로에서 인증 확인
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'error' => '로그인이 필요합니다.',
                'code' => 'UNAUTHENTICATED',
                'redirect' => '/login'
            ], 401);
        }

        return $next($request);
    }
}
