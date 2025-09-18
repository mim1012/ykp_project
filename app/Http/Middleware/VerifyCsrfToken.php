<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [];

    public function __construct($app)
    {
        parent::__construct($app);

        // 개발/테스트 환경에서만 특정 경로 CSRF 제외
        if (config('app.env') !== 'production') {
            $this->except = [
                '/test-api/*',  // Playwright/개발용 테스트 API 경로
                '/api/dev/*',   // 개발용 API
                '/api/sales/*', // 개발/테스트 환경: 세션 인증 유지하되 CSRF 제외
            ];
        }
    }
}
