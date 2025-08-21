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
        $middleware->alias([
            'rbac' => \App\Http\Middleware\RBACMiddleware::class,
            'performance' => \App\Http\Middleware\PerformanceMonitoringMiddleware::class,
        ]);

        // 전역 미들웨어 등록
        $middleware->web(append: [
            \App\Http\Middleware\PerformanceMonitoringMiddleware::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\PerformanceMonitoringMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
