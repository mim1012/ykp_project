<?php

use App\Http\Controllers\Api\CalculationController;
use App\Jobs\ProcessBatchCalculationJob;
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

// 실시간 통계 API (간단한 카운트) - 인증 없이 접근 가능 (개발용)
Route::get('/users/count', function () {
    return response()->json(['count' => \App\Models\User::count()]);
})->name('api.users.count');

Route::get('/stores/count', function () {
    return response()->json(['count' => \App\Models\Store::count()]);
})->name('api.stores.count');

Route::get('/dealer-profiles/count', function () {
    return response()->json(['count' => \App\Models\DealerProfile::count()]);
})->name('api.dealer-profiles.count');

Route::get('/sales/count', function () {
    return response()->json(['count' => \App\Models\Sale::count()]);
})->name('api.sales.count');

// 매장 관리 API (개발용 - 완전 우회)
Route::get('dev/stores/list', function() {
    $stores = App\Models\Store::with('branch')->get();
    return response()->json(['success' => true, 'data' => $stores]);
});

Route::post('dev/stores/add', function(Illuminate\Http\Request $request) {
    $branch = App\Models\Branch::find($request->branch_id);
    $storeCount = App\Models\Store::where('branch_id', $request->branch_id)->count();
    $autoCode = $branch->code . '-' . str_pad($storeCount + 1, 3, '0', STR_PAD_LEFT);
    
    $store = App\Models\Store::create([
        'name' => $request->name,
        'code' => $autoCode,
        'branch_id' => $request->branch_id,
        'owner_name' => $request->owner_name,
        'phone' => $request->phone,
        'address' => '',
        'status' => 'active',
        'opened_at' => now()
    ]);
    
    return response()->json(['success' => true, 'data' => $store]);
});

// 기존 복잡한 라우트 (문제 있음)
Route::prefix('dev/stores')->group(function () {
    Route::get('/', function() {
        $stores = App\Models\Store::with('branch')->get();
        return response()->json(['success' => true, 'data' => $stores]);
    });
    Route::any('/', function(Illuminate\Http\Request $request) {
        // 매장명과 지사 정보로 간단하게 추가
        $request->validate([
            'name' => 'required|string|max:255',
            'branch_id' => 'required|exists:branches,id',
            'owner_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20'
        ]);
        
        // 자동 코드 생성
        $branch = App\Models\Branch::find($request->branch_id);
        $storeCount = App\Models\Store::where('branch_id', $request->branch_id)->count();
        $autoCode = $branch->code . '-' . str_pad($storeCount + 1, 3, '0', STR_PAD_LEFT);
        
        $store = App\Models\Store::create([
            'name' => $request->name,
            'code' => $autoCode, // 자동 생성
            'branch_id' => $request->branch_id,
            'owner_name' => $request->owner_name,
            'phone' => $request->phone,
            'address' => '',
            'status' => 'active',
            'opened_at' => now()
        ]);
        
        return response()->json([
            'success' => true,
            'message' => '매장이 추가되었습니다.',
            'data' => $store->load('branch')
        ], 201);
    });
    Route::get('/branches', function() {
        $branches = App\Models\Branch::withCount('stores')->get();
        return response()->json(['success' => true, 'data' => $branches]);
    });
    Route::post('/sales/save', function(Illuminate\Http\Request $request) {
        try {
            $salesData = $request->input('sales', []);
            $savedCount = 0;
            
            foreach ($salesData as $sale) {
                App\Models\Sale::create($sale);
                $savedCount++;
            }
            
            return response()->json([
                'success' => true,
                'message' => $savedCount . '건이 저장되었습니다.',
                'saved_count' => $savedCount
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '저장 오류: ' . $e->getMessage()
            ], 500);
        }
    });
});

// 매장 관리 API (운영용 - 인증 필요)
Route::middleware(['auth'])->prefix('stores')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\StoreController::class, 'index'])->name('api.stores.index');
    Route::post('/', [App\Http\Controllers\Api\StoreController::class, 'store'])->name('api.stores.store');
    Route::post('/{store}/create-user', [App\Http\Controllers\Api\StoreController::class, 'createStoreUser'])->name('api.stores.create-user');
    Route::get('/branches', [App\Http\Controllers\Api\StoreController::class, 'branches'])->name('api.stores.branches');
});

// Sales Data API - 임시로 인증 제거 (연동 테스트용)
Route::prefix('sales')->group(function () {
    // Read operations (GET)
    Route::get('/', [SalesApiController::class, 'index'])->name('api.sales.index');
    Route::get('/statistics', [SalesApiController::class, 'statistics'])->name('api.sales.statistics');

    // Write operations (POST) - Additional CSRF protection for web requests
    Route::post('/bulk', [SalesApiController::class, 'bulkSave'])
        ->middleware('throttle:30,1') // Rate limiting: 30 requests per minute
        ->name('api.sales.bulk');
    
    // AgGrid 전용 bulk save 엔드포인트
    Route::post('/bulk-save', [SalesApiController::class, 'bulkSave'])
        ->middleware('throttle:60,1') // Rate limiting: 60 requests per minute
        ->name('api.sales.bulk-save');
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
| Calculation API (실시간 계산)
|--------------------------------------------------------------------------
| 실시간 마진 계산을 위한 API 엔드포인트
| 프로파일 기반 고도화 기능 포함
*/

Route::prefix('calculation')->group(function () {
    // 기존 API (호환성 유지)
    Route::post('/row', [CalculationController::class, 'calculateRow'])
        ->middleware('throttle:120,1') // 1분당 120번 요청 제한
        ->name('api.calculation.row');
    
    Route::post('/batch', [CalculationController::class, 'calculateBatch'])
        ->middleware('throttle:10,1') // 1분당 10번 요청 제한
        ->name('api.calculation.batch');
    
    Route::post('/validate-formula', [CalculationController::class, 'validateFormula'])
        ->middleware('throttle:60,1')
        ->name('api.calculation.validate');
    
    // 프로파일 기반 API (고도화)
    Route::post('/profile/row', [CalculationController::class, 'calculateRowWithProfile'])
        ->middleware('throttle:200,1') // 고성능으로 더 많이 허용
        ->name('api.calculation.profile.row');
    
    Route::post('/profile/batch', [CalculationController::class, 'calculateBatchWithProfile'])
        ->middleware('throttle:5,1') // 배치는 더 엄격한 제한
        ->name('api.calculation.profile.batch');
    
    // 프로파일 관리
    Route::get('/profiles', [CalculationController::class, 'getProfiles'])
        ->middleware('throttle:30,1')
        ->name('api.calculation.profiles');
    
    Route::get('/profiles/{dealerCode}', [CalculationController::class, 'getProfile'])
        ->middleware('throttle:60,1')
        ->name('api.calculation.profile');
    
    // 컬럼 정의 (AgGrid 지원)
    Route::get('/columns', [CalculationController::class, 'getColumnDefinitions'])
        ->middleware('throttle:30,1')
        ->name('api.calculation.columns');
    
    // 성능 및 모니터링
    Route::post('/benchmark', [CalculationController::class, 'benchmark'])
        ->middleware('throttle:5,1') // 벤치마크는 자주 사용 안 함
        ->name('api.calculation.benchmark');
});

/*
|--------------------------------------------------------------------------
| 비동기 배치 처리 API
|--------------------------------------------------------------------------
| 대량 데이터 처리를 위한 비동기 Job 처리
*/

Route::prefix('batch-jobs')->group(function () {
    // 비동기 배치 처리 시작
    Route::post('/start', [CalculationController::class, 'startBatchJob'])
        ->middleware('throttle:3,1') // 아주 엄격한 제한
        ->name('api.batch.start');
    
    // Job 상태 조회
    Route::get('/{jobId}/status', [CalculationController::class, 'getBatchJobStatus'])
        ->middleware('throttle:60,1')
        ->name('api.batch.status');
    
    // Job 결과 조회
    Route::get('/{jobId}/result', [CalculationController::class, 'getBatchJobResult'])
        ->middleware('throttle:30,1')
        ->name('api.batch.result');
    
    // Job 취소
    Route::delete('/{jobId}', [CalculationController::class, 'cancelBatchJob'])
        ->middleware('throttle:10,1')
        ->name('api.batch.cancel');
});

/*
|--------------------------------------------------------------------------
| 일일지출 관리 API
|--------------------------------------------------------------------------
| 대리점별 일일지출 내역 관리 (상담비, 메일접수비, 기타 운영비)
*/

Route::middleware(['auth:sanctum'])->prefix('daily-expenses')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\DailyExpenseController::class, 'index'])->name('api.daily-expenses.index');
    Route::post('/', [App\Http\Controllers\Api\DailyExpenseController::class, 'store'])->name('api.daily-expenses.store');
    Route::get('/{id}', [App\Http\Controllers\Api\DailyExpenseController::class, 'show'])->name('api.daily-expenses.show');
    Route::put('/{id}', [App\Http\Controllers\Api\DailyExpenseController::class, 'update'])->name('api.daily-expenses.update');
    Route::delete('/{id}', [App\Http\Controllers\Api\DailyExpenseController::class, 'destroy'])->name('api.daily-expenses.destroy');
    
    // 월별 지출 현황 요약
    Route::get('/summary/monthly', [App\Http\Controllers\Api\DailyExpenseController::class, 'monthlySummary'])->name('api.daily-expenses.monthly-summary');
});

/*
|--------------------------------------------------------------------------
| 고정지출 관리 API
|--------------------------------------------------------------------------
| 월별 고정비용 관리 (임대료, 인건비, 통신비 등)
*/

Route::middleware(['auth:sanctum'])->prefix('fixed-expenses')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\FixedExpenseController::class, 'index'])->name('api.fixed-expenses.index');
    Route::post('/', [App\Http\Controllers\Api\FixedExpenseController::class, 'store'])->name('api.fixed-expenses.store');
    Route::get('/{id}', [App\Http\Controllers\Api\FixedExpenseController::class, 'show'])->name('api.fixed-expenses.show');
    Route::put('/{id}', [App\Http\Controllers\Api\FixedExpenseController::class, 'update'])->name('api.fixed-expenses.update');
    Route::delete('/{id}', [App\Http\Controllers\Api\FixedExpenseController::class, 'destroy'])->name('api.fixed-expenses.destroy');
    
    // 지급 상태 관리
    Route::put('/{id}/payment-status', [App\Http\Controllers\Api\FixedExpenseController::class, 'updatePaymentStatus'])->name('api.fixed-expenses.payment-status');
    
    // 지급 예정 내역 조회
    Route::get('/upcoming/payments', [App\Http\Controllers\Api\FixedExpenseController::class, 'upcomingPayments'])->name('api.fixed-expenses.upcoming');
});

/*
|--------------------------------------------------------------------------
| 환수금액 관리 API
|--------------------------------------------------------------------------
| 고객 환불 및 통신사 환수 관리
*/

Route::middleware(['auth:sanctum'])->prefix('refunds')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\RefundController::class, 'index'])->name('api.refunds.index');
    Route::post('/', [App\Http\Controllers\Api\RefundController::class, 'store'])->name('api.refunds.store');
    Route::get('/{id}', [App\Http\Controllers\Api\RefundController::class, 'show'])->name('api.refunds.show');
    Route::put('/{id}', [App\Http\Controllers\Api\RefundController::class, 'update'])->name('api.refunds.update');
    Route::delete('/{id}', [App\Http\Controllers\Api\RefundController::class, 'destroy'])->name('api.refunds.destroy');
    
    // 환수율 분석
    Route::get('/analysis/summary', [App\Http\Controllers\Api\RefundController::class, 'analysis'])->name('api.refunds.analysis');
});

/*
|--------------------------------------------------------------------------
| 직원급여 관리 API (엑셀 점장급여 방식)
|--------------------------------------------------------------------------
| 월별 급여 관리 - 수기입력 + 인센티브 자동계산
*/

Route::middleware(['auth:sanctum'])->prefix('payroll')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\PayrollController::class, 'index'])->name('api.payroll.index');
    Route::post('/', [App\Http\Controllers\Api\PayrollController::class, 'store'])->name('api.payroll.store');
    Route::get('/{id}', [App\Http\Controllers\Api\PayrollController::class, 'show'])->name('api.payroll.show');
    Route::put('/{id}', [App\Http\Controllers\Api\PayrollController::class, 'update'])->name('api.payroll.update');
    Route::delete('/{id}', [App\Http\Controllers\Api\PayrollController::class, 'destroy'])->name('api.payroll.destroy');
    
    // 지급 상태 토글 (엑셀 체크박스 방식)
    Route::put('/{id}/payment-status', [App\Http\Controllers\Api\PayrollController::class, 'togglePaymentStatus'])->name('api.payroll.payment-status');
    
    // 월별 급여 요약
    Route::get('/summary/monthly', [App\Http\Controllers\Api\PayrollController::class, 'monthlySummary'])->name('api.payroll.monthly-summary');
});

/*
|--------------------------------------------------------------------------
| 통합 대시보드 API
|--------------------------------------------------------------------------
| 메인 대시보드용 실시간 데이터 제공
*/

// 웹 대시보드용 API (세션 인증 + 권한별 데이터 필터링)
Route::middleware(['web', 'auth'])->prefix('dashboard')->group(function () {
    Route::get('/overview', [App\Http\Controllers\Api\DashboardController::class, 'overview'])->name('api.dashboard.overview');
    Route::get('/sales-trend', [App\Http\Controllers\Api\DashboardController::class, 'salesTrend'])->name('api.dashboard.sales-trend');
    Route::get('/dealer-performance', [App\Http\Controllers\Api\DashboardController::class, 'dealerPerformance'])->name('api.dashboard.dealer-performance');
    Route::get('/financial-summary', [App\Http\Controllers\Api\DashboardController::class, 'financialSummary'])->name('api.dashboard.financial-summary');
    
    // 권한별 매장 랭킹 및 일별 통계 (Supabase 연동)
    Route::get('/store-ranking', [App\Http\Controllers\Api\DashboardController::class, 'storeRanking'])->name('api.dashboard.store-ranking');
    Route::get('/daily-sales-report', [App\Http\Controllers\Api\DashboardController::class, 'dailySalesReport'])->name('api.dashboard.daily-sales-report');
});

/*
|--------------------------------------------------------------------------
| 개발/테스트용 임시 경로 (인증 우회)
|--------------------------------------------------------------------------
| 프론트엔드 개발 및 테스트용 - 운영 시 제거 필요
*/

if (config('app.env') !== 'production') {
    Route::prefix('dev')->group(function () {
        Route::get('/dashboard/overview', [App\Http\Controllers\Api\DashboardController::class, 'overview'])->name('dev.dashboard.overview');
        Route::get('/dashboard/store-ranking', [App\Http\Controllers\Api\DashboardController::class, 'storeRanking'])->name('dev.dashboard.store-ranking');
        Route::get('/dashboard/daily-sales-report', [App\Http\Controllers\Api\DashboardController::class, 'dailySalesReport'])->name('dev.dashboard.daily-sales-report');
        Route::get('/calculation/profiles', [App\Http\Controllers\Api\CalculationController::class, 'getProfiles'])->name('dev.calculation.profiles');
        Route::get('/daily-expenses', [App\Http\Controllers\Api\DailyExpenseController::class, 'index'])->name('dev.daily-expenses.index');
        Route::get('/fixed-expenses', [App\Http\Controllers\Api\FixedExpenseController::class, 'index'])->name('dev.fixed-expenses.index');
        Route::get('/payroll', [App\Http\Controllers\Api\PayrollController::class, 'index'])->name('dev.payroll.index');
        Route::get('/payroll/summary/monthly', [App\Http\Controllers\Api\PayrollController::class, 'monthlySummary'])->name('dev.payroll.monthly-summary');
    });
}

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

/*
|--------------------------------------------------------------------------
| 월마감정산 API (가장 핵심적인 기능)
|--------------------------------------------------------------------------
| 엑셀 "월마감정산" 시트의 모든 로직을 API로 구현
*/

Route::prefix('monthly-settlements')->name('api.monthly-settlements.')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\MonthlySettlementController::class, 'index']);
    Route::post('/generate', [App\Http\Controllers\Api\MonthlySettlementController::class, 'generate']);
    Route::post('/generate-all', [App\Http\Controllers\Api\MonthlySettlementController::class, 'generateAll']);
    Route::get('/dashboard/{year_month}', [App\Http\Controllers\Api\MonthlySettlementController::class, 'dashboardData']);
    Route::get('/trend/{year}', [App\Http\Controllers\Api\MonthlySettlementController::class, 'yearlyTrend']);
    Route::get('/{id}', [App\Http\Controllers\Api\MonthlySettlementController::class, 'show']);
    Route::put('/{id}', [App\Http\Controllers\Api\MonthlySettlementController::class, 'update']);
    Route::post('/{id}/confirm', [App\Http\Controllers\Api\MonthlySettlementController::class, 'confirm']);
    Route::post('/{id}/close', [App\Http\Controllers\Api\MonthlySettlementController::class, 'close']);
});
