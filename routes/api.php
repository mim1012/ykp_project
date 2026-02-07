<?php

use App\Helpers\DatabaseHelper;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CalculationController;
use App\Http\Controllers\Api\StoreManagementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SalesApiController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// API Routes

// Authentication check route
Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// 실시간 통계 API (간단한 카운트) - 대시보드용
Route::get('/users/count', function () {
    try {
        return response()->json(['success' => true, 'count' => \App\Models\User::count()]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
})->name('api.users.count');

Route::get('/stores/count', function () {
    try {
        return response()->json(['success' => true, 'count' => \App\Models\Store::count()]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
})->name('api.stores.count');

Route::get('/sales/count', function () {
    try {
        return response()->json(['success' => true, 'count' => \App\Models\Sale::count()]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
})->name('api.sales.count');

// 매장 관리 API (운영용 - 세션 기반 인증)
Route::middleware(['web', 'auth'])->prefix('stores')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\StoreController::class, 'index'])->name('api.stores.index');
    Route::post('/', [App\Http\Controllers\Api\StoreController::class, 'store'])->name('api.stores.store');
    Route::put('/{store}', [App\Http\Controllers\Api\StoreController::class, 'update'])->name('api.stores.update');
    Route::delete('/{store}', [App\Http\Controllers\Api\StoreController::class, 'destroy'])->name('api.stores.destroy');
    Route::post('/{store}/create-user', [App\Http\Controllers\Api\StoreController::class, 'createStoreUser'])->name('api.stores.create-user');
    Route::post('/{store}/create-account', [App\Http\Controllers\Api\StoreController::class, 'createAccount'])->name('api.stores.create-account');
    // 새로운 계정 관리 엔드포인트
    Route::get('/{store}/account', [App\Http\Controllers\Api\StoreController::class, 'getAccount'])->name('api.stores.get-account');
    Route::post('/{store}/account', [App\Http\Controllers\Api\StoreController::class, 'createStoreAccount'])->name('api.stores.account');
    Route::get('/{store}/performance', [App\Http\Controllers\Api\StoreController::class, 'performance'])->name('api.stores.performance');
    Route::get('/branches', [App\Http\Controllers\Api\StoreController::class, 'branches'])->name('api.stores.branches');
});

// Sales Data API - 통일된 인증 및 RBAC 보호
Route::middleware(['web', 'auth', 'rbac'])->prefix('sales')->group(function () {
    // Read operations (GET)
    Route::get('/', [SalesApiController::class, 'index'])->name('api.sales.index');
    Route::get('/statistics', [SalesApiController::class, 'statistics'])->name('api.sales.statistics');

    // Write operations (POST)
    Route::post('/bulk', [SalesApiController::class, 'bulkSave'])
        ->middleware('throttle:30,1')
        ->name('api.sales.bulk');

    // AgGrid 전용 bulk save 엔드포인트
    Route::post('/bulk-save', [SalesApiController::class, 'bulkSave'])
        ->middleware('throttle:60,1')
        ->name('api.sales.bulk-save');

    // Bulk delete endpoint for sales data
    Route::post('/bulk-delete', [SalesApiController::class, 'bulkDelete'])
        ->middleware('throttle:30,1')
        ->name('api.sales.bulk-delete');
});

// Report API - Requires authentication and RBAC
Route::middleware(['web', 'auth', 'rbac'])->prefix('report')->group(function () {
    Route::get('/summary', [App\Http\Controllers\ReportController::class, 'summary'])
        ->name('api.report.summary');
    Route::get('/export.xlsx', [App\Http\Controllers\ReportController::class, 'exportExcel'])
        ->name('api.report.excel');
    Route::get('/export.pdf', [App\Http\Controllers\ReportController::class, 'exportPDF'])
        ->name('api.report.pdf');
});

// Calculation API (실시간 계산)
Route::prefix('calculation')->group(function () {
    // 기존 API (호환성 유지)
    Route::post('/row', [CalculationController::class, 'calculateRow'])
        ->middleware('throttle:120,1')
        ->name('api.calculation.row');

    Route::post('/batch', [CalculationController::class, 'calculateBatch'])
        ->middleware('throttle:10,1')
        ->name('api.calculation.batch');

    Route::post('/validate-formula', [CalculationController::class, 'validateFormula'])
        ->middleware('throttle:60,1')
        ->name('api.calculation.validate');

    // 프로파일 기반 API (고도화)
    Route::post('/profile/row', [CalculationController::class, 'calculateRowWithProfile'])
        ->middleware('throttle:200,1')
        ->name('api.calculation.profile.row');

    Route::post('/profile/batch', [CalculationController::class, 'calculateBatchWithProfile'])
        ->middleware('throttle:5,1')
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
        ->middleware('throttle:5,1')
        ->name('api.calculation.benchmark');
});

// 비동기 배치 처리 API
Route::prefix('batch-jobs')->group(function () {
    Route::post('/start', [CalculationController::class, 'startBatchJob'])
        ->middleware('throttle:3,1')
        ->name('api.batch.start');

    Route::get('/{jobId}/status', [CalculationController::class, 'getBatchJobStatus'])
        ->middleware('throttle:60,1')
        ->name('api.batch.status');

    Route::get('/{jobId}/result', [CalculationController::class, 'getBatchJobResult'])
        ->middleware('throttle:30,1')
        ->name('api.batch.result');

    Route::delete('/{jobId}', [CalculationController::class, 'cancelBatchJob'])
        ->middleware('throttle:10,1')
        ->name('api.batch.cancel');
});

// 일일지출 관리 API
Route::middleware(['web', 'auth'])->prefix('daily-expenses')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\DailyExpenseController::class, 'index'])->name('api.daily-expenses.index');
    Route::post('/', [App\Http\Controllers\Api\DailyExpenseController::class, 'store'])->name('api.daily-expenses.store');
    Route::get('/{id}', [App\Http\Controllers\Api\DailyExpenseController::class, 'show'])->name('api.daily-expenses.show');
    Route::put('/{id}', [App\Http\Controllers\Api\DailyExpenseController::class, 'update'])->name('api.daily-expenses.update');
    Route::delete('/{id}', [App\Http\Controllers\Api\DailyExpenseController::class, 'destroy'])->name('api.daily-expenses.destroy');

    // 월별 지출 현황 요약
    Route::get('/summary/monthly', [App\Http\Controllers\Api\DailyExpenseController::class, 'monthlySummary'])->name('api.daily-expenses.monthly-summary');
});

// 고정지출 관리 API
Route::middleware(['web', 'auth'])->prefix('fixed-expenses')->group(function () {
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

// 환수금액 관리 API
Route::middleware(['web', 'auth'])->prefix('refunds')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\RefundController::class, 'index'])->name('api.refunds.index');
    Route::post('/', [App\Http\Controllers\Api\RefundController::class, 'store'])->name('api.refunds.store');
    Route::get('/{id}', [App\Http\Controllers\Api\RefundController::class, 'show'])->name('api.refunds.show');
    Route::put('/{id}', [App\Http\Controllers\Api\RefundController::class, 'update'])->name('api.refunds.update');
    Route::delete('/{id}', [App\Http\Controllers\Api\RefundController::class, 'destroy'])->name('api.refunds.destroy');

    // 환수율 분석
    Route::get('/analysis/summary', [App\Http\Controllers\Api\RefundController::class, 'analysis'])->name('api.refunds.analysis');
});

// 직원급여 관리 API (엑셀 점장급여 방식)
Route::middleware(['web', 'auth'])->prefix('payroll')->group(function () {
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

// 통합 대시보드 API (세션 기반 인증)
Route::middleware(['web', 'auth'])->prefix('dashboard')->group(function () {
    Route::get('/overview', [App\Http\Controllers\Api\DashboardController::class, 'overview']);
    Route::get('/store-ranking', [App\Http\Controllers\Api\DashboardController::class, 'storeRanking']);
    Route::get('/financial-summary', [App\Http\Controllers\Api\DashboardController::class, 'financialSummary']);
    Route::get('/dealer-performance', [App\Http\Controllers\Api\DashboardController::class, 'dealerPerformance']);
    Route::get('/rankings', [App\Http\Controllers\Api\DashboardController::class, 'rankings']);
    Route::get('/top-list', [App\Http\Controllers\Api\DashboardController::class, 'topList']);
    Route::get('/sales-trend', [App\Http\Controllers\Api\DashboardController::class, 'salesTrend']);
});

// Statistics API group for KPI and other statistics
Route::middleware(['web', 'auth'])->prefix('statistics')->group(function () {
    Route::get('/kpi', [App\Http\Controllers\Api\DashboardController::class, 'kpi']);
});

// User Profile & Password Management
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/users/change-password', [AuthController::class, 'changePassword'])
        ->name('api.users.change-password');
});

// User Management API (본사 전용)
Route::middleware(['web', 'auth', 'rbac'])->prefix('users')->group(function () {
    Route::get('/', [UserManagementController::class, 'index'])->name('api.users.index');
    Route::post('/', [UserManagementController::class, 'store'])->name('api.users.store');
    Route::put('/{user}', [UserManagementController::class, 'update'])->name('api.users.update');
    Route::delete('/{user}', [UserManagementController::class, 'destroy'])->name('api.users.destroy');

    // 지사 목록 (통계 페이지용 - 단순화)
    Route::get('/branches', function () {
        try {
            $branches = DatabaseHelper::executeWithRetry(function () {
                return \App\Models\Branch::select('id', 'name', 'code', 'status')->get();
            });

            $branchData = [];
            foreach ($branches as $branch) {
                $storeCount = DatabaseHelper::executeWithRetry(function () use ($branch) {
                    return \App\Models\Store::where('branch_id', $branch->id)->count();
                });

                $branchData[] = [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'code' => $branch->code,
                    'users_count' => 0,
                    'stores_count' => $storeCount,
                ];
            }

            return response()->json(['success' => true, 'data' => $branchData]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    })->name('api.users.branches');
});

// Profile API
Route::get('/api/profile', function () {
    $user = \Illuminate\Support\Facades\Auth::user();

    if (! $user) {
        return response()->json([
            'success' => true,
            'data' => [
                'id' => null,
                'name' => '게스트',
                'email' => null,
                'role' => 'guest',
                'branch_id' => null,
                'store_id' => null,
            ],
        ]);
    }

    return response()->json([
        'success' => true,
        'data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'branch_id' => $user->branch_id,
            'store_id' => $user->store_id,
        ],
    ]);
})->name('api.profile');

// 활동 로그 API
Route::middleware(['web', 'auth'])->prefix('activities')->group(function () {
    Route::get('/recent', [App\Http\Controllers\ActivityController::class, 'recent'])->name('api.activities.recent');
    Route::post('/log', [App\Http\Controllers\ActivityController::class, 'log'])->name('api.activities.log');
});

// 대리점 관리 API (Dealer Profile Management)
Route::middleware(['web', 'auth'])->prefix('dealers')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\DealerProfileController::class, 'index'])->name('api.dealers.index');
    Route::post('/', [App\Http\Controllers\Api\DealerProfileController::class, 'store'])->name('api.dealers.store');
    Route::put('/{id}', [App\Http\Controllers\Api\DealerProfileController::class, 'update'])->name('api.dealers.update');
    Route::delete('/{id}', [App\Http\Controllers\Api\DealerProfileController::class, 'destroy'])->name('api.dealers.destroy');
});

// 판매 데이터 내보내기/가져오기 API
Route::middleware(['web', 'auth'])->prefix('sales-export')->group(function () {
    Route::get('/csv', [App\Http\Controllers\Api\SalesExportController::class, 'exportCsv'])->name('api.sales.export.csv');
    Route::get('/template', [App\Http\Controllers\Api\SalesExportController::class, 'downloadTemplate'])->name('api.sales.export.template');
    Route::post('/import', [App\Http\Controllers\Api\SalesExportController::class, 'importCsv'])->name('api.sales.import.csv');
});

// 통신사 관리 API (Carrier Management)
Route::middleware(['web', 'auth'])->prefix('carriers')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\CarrierController::class, 'index'])->name('api.carriers.index');
    Route::post('/', [App\Http\Controllers\Api\CarrierController::class, 'store'])->name('api.carriers.store');
    Route::put('/{id}', [App\Http\Controllers\Api\CarrierController::class, 'update'])->name('api.carriers.update');
    Route::delete('/{id}', [App\Http\Controllers\Api\CarrierController::class, 'destroy'])->name('api.carriers.destroy');
});

// 월마감정산 API
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

// 매장별 통계 엑셀 다운로드 (본사 전용)
Route::middleware(['web', 'auth'])->get('/reports/store-statistics', [App\Http\Controllers\Api\ReportController::class, 'exportStoreStatistics'])
    ->name('api.reports.store-statistics');

// 매장 관리 API (Store Management)
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/stores', [App\Http\Controllers\Api\StoreManagementController::class, 'index'])
        ->name('api.stores-management.index');

    Route::patch('/stores/{id}', [App\Http\Controllers\Api\StoreManagementController::class, 'update'])
        ->name('api.stores-management.update');

    Route::put('/stores/{id}/classification', [App\Http\Controllers\Api\StoreManagementController::class, 'updateClassification'])
        ->name('api.stores-management.update-classification');

    Route::put('/stores/{id}/business-info', [App\Http\Controllers\Api\StoreManagementController::class, 'updateBusinessInfo'])
        ->name('api.stores-management.update-business-info');

    Route::get('/branches/list', [App\Http\Controllers\Api\BranchController::class, 'list'])
        ->name('api.branches.list');
});

// 고객 관리 API (Customer Management - CRM)
Route::middleware(['web', 'auth', 'rbac'])->prefix('customers')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\CustomerController::class, 'index'])->name('api.customers.index');
    Route::post('/', [App\Http\Controllers\Api\CustomerController::class, 'store'])->name('api.customers.store');
    Route::get('/statistics', [App\Http\Controllers\Api\CustomerController::class, 'statistics'])->name('api.customers.statistics');
    Route::get('/{id}', [App\Http\Controllers\Api\CustomerController::class, 'show'])->name('api.customers.show');
    Route::put('/{id}', [App\Http\Controllers\Api\CustomerController::class, 'update'])->name('api.customers.update');
    Route::delete('/{id}', [App\Http\Controllers\Api\CustomerController::class, 'destroy'])->name('api.customers.destroy');
});

// 매장 통계 API (Store Statistics)
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/stores/{id}/statistics', [App\Http\Controllers\Api\StoreStatisticsController::class, 'index'])
        ->name('api.stores.statistics');
    Route::get('/stores/{id}/sales/export', [App\Http\Controllers\Api\StoreStatisticsController::class, 'exportSales'])
        ->name('api.stores.sales.export');
});

// Q&A 게시판 API
Route::middleware(['web', 'auth'])->prefix('qna')->group(function () {
    Route::get('/posts', [App\Http\Controllers\Api\QnaPostController::class, 'index'])
        ->name('api.qna.posts.index');
    Route::post('/posts', [App\Http\Controllers\Api\QnaPostController::class, 'store'])
        ->name('api.qna.posts.store');
    Route::get('/posts/{id}', [App\Http\Controllers\Api\QnaPostController::class, 'show'])
        ->name('api.qna.posts.show');
    Route::put('/posts/{id}', [App\Http\Controllers\Api\QnaPostController::class, 'update'])
        ->name('api.qna.posts.update');
    Route::delete('/posts/{id}', [App\Http\Controllers\Api\QnaPostController::class, 'destroy'])
        ->name('api.qna.posts.destroy');
    Route::post('/posts/{id}/replies', [App\Http\Controllers\Api\QnaPostController::class, 'addReply'])
        ->name('api.qna.posts.replies.store');
    Route::post('/posts/{id}/close', [App\Http\Controllers\Api\QnaPostController::class, 'close'])
        ->name('api.qna.posts.close');
});

// 공지사항 API (Notice Board)
Route::middleware(['web', 'auth'])->prefix('notices')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\NoticePostController::class, 'index'])
        ->name('api.notices.index');
    Route::post('/', [App\Http\Controllers\Api\NoticePostController::class, 'store'])
        ->name('api.notices.store');
    Route::get('/{id}', [App\Http\Controllers\Api\NoticePostController::class, 'show'])
        ->name('api.notices.show');
    Route::put('/{id}', [App\Http\Controllers\Api\NoticePostController::class, 'update'])
        ->name('api.notices.update');
    Route::delete('/{id}', [App\Http\Controllers\Api\NoticePostController::class, 'destroy'])
        ->name('api.notices.destroy');
    Route::post('/{id}/toggle-pin', [App\Http\Controllers\Api\NoticePostController::class, 'togglePin'])
        ->name('api.notices.toggle-pin');
});

// 지출 내역 API (Expense Management)
Route::middleware(['web', 'auth', 'rbac'])->prefix('expenses')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\ExpenseController::class, 'index'])->name('api.expenses.index');
    Route::post('/', [App\Http\Controllers\Api\ExpenseController::class, 'store'])->name('api.expenses.store');
    Route::get('/summary', [App\Http\Controllers\Api\ExpenseController::class, 'summary'])->name('api.expenses.summary');
    Route::get('/{id}', [App\Http\Controllers\Api\ExpenseController::class, 'show'])->name('api.expenses.show');
    Route::put('/{id}', [App\Http\Controllers\Api\ExpenseController::class, 'update'])->name('api.expenses.update');
    Route::delete('/{id}', [App\Http\Controllers\Api\ExpenseController::class, 'destroy'])->name('api.expenses.destroy');
});

// 이미지 업로드 API
Route::middleware(['web', 'auth'])->prefix('images')->group(function () {
    Route::post('/upload', [App\Http\Controllers\Api\ImageController::class, 'upload'])->name('api.images.upload');
    Route::delete('/{id}', [App\Http\Controllers\Api\ImageController::class, 'destroy'])->name('api.images.destroy');
});

// 매장 월 목표 API
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/my-goal', [App\Http\Controllers\Api\GoalController::class, 'show'])
        ->name('api.my-goal.show');
    Route::post('/my-goal', [App\Http\Controllers\Api\GoalController::class, 'store'])
        ->name('api.my-goal.store');
});
