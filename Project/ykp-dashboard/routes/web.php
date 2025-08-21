<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

// Authentication routes (accessible to guests only)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('auth.login');
    Route::post('/login', [AuthController::class, 'login']);

    // Only show registration in non-production environments
    if (config('app.env') !== 'production') {
        Route::get('/register', [AuthController::class, 'showRegistrationForm'])->name('auth.register');
        Route::post('/register', [AuthController::class, 'register']);
    }
});

// Logout route (accessible to authenticated users only)
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('auth.logout');

// Root route - redirect based on authentication status
Route::get('/', function () {
    if (auth()->check()) {
        return view('modern-dashboard-optimized');
    }

    return redirect()->route('auth.login');
})->name('home');

/*
|--------------------------------------------------------------------------
| Protected Dashboard Routes
|--------------------------------------------------------------------------
*/

// All dashboard routes require authentication and RBAC
Route::middleware(['auth', 'rbac'])->group(function () {
    // Dashboard home
    Route::get('/dashboard', function () {
        return view('modern-dashboard-optimized');
    })->name('dashboard.home');

    // Excel 스타일 판매 데이터 입력
    Route::get('/sales/excel-input', function () {
        return view('sales.excel-input');
    })->name('sales.excel-input');

    // 개통표 스타일 고급 입력
    Route::get('/sales/advanced-input', function () {
        return view('sales.advanced-input');
    })->name('sales.advanced-input');

    // 개선된 개통표 입력
    Route::get('/sales/improved-input', function () {
        return view('sales.improved-input');
    })->name('sales.improved-input');

    // Additional sales input views
    Route::get('/sales/advanced-input-enhanced', function () {
        return view('sales.advanced-input-enhanced');
    })->name('sales.advanced-input-enhanced');

    Route::get('/sales/advanced-input-pro', function () {
        return view('sales.advanced-input-pro');
    })->name('sales.advanced-input-pro');

    Route::get('/sales/advanced-input-simple', function () {
        return view('sales.advanced-input-simple');
    })->name('sales.advanced-input-simple');
});

/*
|--------------------------------------------------------------------------
| API Routes for Authentication
|--------------------------------------------------------------------------
*/

// API route to get current user info (for AJAX requests)
Route::middleware('auth')->get('/api/user', [AuthController::class, 'user'])->name('api.user');
