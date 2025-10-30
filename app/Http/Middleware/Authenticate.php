<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // For API requests, return null (will return 401 JSON response)
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }

        // For web requests, redirect to login with session expired message
        return route('login');
    }

    /**
     * Handle an unauthenticated user.
     */
    protected function unauthenticated($request, array $guards)
    {
        // For web requests, add session expired message
        if (! $request->expectsJson() && ! $request->is('api/*')) {
            session()->flash('message', '세션이 만료되었습니다. 다시 로그인해주세요.');
            session()->flash('type', 'error');
        }

        parent::unauthenticated($request, $guards);
    }
}
