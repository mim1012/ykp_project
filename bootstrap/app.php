<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register custom authenticate middleware
        $middleware->redirectGuestsTo(fn () => route('login'));

        $middleware->alias([
            'rbac' => \App\Http\Middleware\RBACMiddleware::class,
            'performance' => \App\Http\Middleware\PerformanceMonitoringMiddleware::class,
            'api.auth' => \App\Http\Middleware\ApiAuthenticate::class,
            'auth' => \App\Http\Middleware\Authenticate::class,
        ]);

        // 프록시 신뢰 미들웨어 추가 (Railway 환경용)
        $middleware->trustProxies(at: '*');

        // 전역 미들웨어 등록
        $middleware->web(append: [
            \App\Http\Middleware\PerformanceMonitoringMiddleware::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\PerformanceMonitoringMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sentry integration
        $exceptions->reportable(function (Throwable $e) {
            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }
        });
    })->create();
