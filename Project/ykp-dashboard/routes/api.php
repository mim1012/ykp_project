<?php

use App\Http\Controllers\SalesApiController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| All API routes require authentication and CSRF protection for security
*/

// Authentication check route
Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Protected API Routes
|--------------------------------------------------------------------------
*/

// Sales Data API - Requires authentication and RBAC
Route::middleware(['auth', 'rbac'])->prefix('sales')->group(function () {
    // Read operations (GET)
    Route::get('/', [SalesApiController::class, 'index'])->name('api.sales.index');
    Route::get('/statistics', [SalesApiController::class, 'statistics'])->name('api.sales.statistics');

    // Write operations (POST) - Additional CSRF protection for web requests
    Route::post('/bulk', [SalesApiController::class, 'bulkSave'])
        ->middleware('throttle:30,1') // Rate limiting: 30 requests per minute
        ->name('api.sales.bulk');
});

// Report API - Requires authentication and RBAC
Route::middleware(['auth', 'rbac'])->prefix('report')->group(function () {
    Route::get('/summary', [App\Http\Controllers\ReportController::class, 'summary'])
        ->name('api.report.summary');
    Route::get('/export.xlsx', [App\Http\Controllers\ReportController::class, 'exportExcel'])
        ->name('api.report.excel');
    Route::get('/export.pdf', [App\Http\Controllers\ReportController::class, 'exportPDF'])
        ->name('api.report.pdf');
});

/*
|--------------------------------------------------------------------------
| Web API Routes (with CSRF protection)
|--------------------------------------------------------------------------
| These routes are called from the dashboard and require CSRF tokens
*/

// Additional web-based API endpoints that require CSRF protection
Route::middleware(['web', 'auth', 'rbac'])->prefix('api')->group(function () {
    // Dashboard specific endpoints
    Route::get('/dashboard/stats', function (Request $request) {
        // Redirect to main statistics endpoint for consistency
        return redirect()->route('api.sales.statistics');
    })->name('api.dashboard.stats');

    // User profile endpoint
    Route::get('/profile', function (Request $request) {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'branch' => $user->branch?->name,
            'store' => $user->store?->name,
            'permissions' => [
                'can_view_all_stores' => $user->isHeadquarters(),
                'can_view_branch_stores' => $user->isBranch(),
                'accessible_store_ids' => $user->getAccessibleStoreIds(),
            ],
        ]);
    })->name('api.profile');
});

/*
|--------------------------------------------------------------------------
| User Management API (본사 전용)
|--------------------------------------------------------------------------
| 본사만 지사/매장 사용자 계정을 생성, 수정, 삭제할 수 있습니다.
*/

Route::middleware(['web', 'auth', 'rbac'])->prefix('api/users')->group(function () {
    // 사용자 목록 조회
    Route::get('/', [UserManagementController::class, 'index'])->name('api.users.index');
    
    // 사용자 생성
    Route::post('/', [UserManagementController::class, 'store'])->name('api.users.store');
    
    // 사용자 정보 수정
    Route::put('/{user}', [UserManagementController::class, 'update'])->name('api.users.update');
    
    // 사용자 삭제
    Route::delete('/{user}', [UserManagementController::class, 'destroy'])->name('api.users.destroy');
    
    // 지사 목록 (사용자 생성 시 필요)
    Route::get('/branches', [UserManagementController::class, 'getBranches'])->name('api.users.branches');
    
    // 매장 목록 (특정 지사의 매장들)
    Route::get('/stores', [UserManagementController::class, 'getStores'])->name('api.users.stores');
});
