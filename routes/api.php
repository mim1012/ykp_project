<?php

use App\Helpers\DatabaseHelper;
use App\Http\Controllers\Api\CalculationController;
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

// 실시간 통계 API (간단한 카운트) - 대시보드용 (인증 없음)
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

// 매장 관리 API (개발용 - 안전한 라우트)
Route::prefix('dev/stores')->group(function () {
    Route::get('/', function () {
        try {
            $stores = App\Models\Store::with('branch')->get();

            return response()->json(['success' => true, 'data' => $stores]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    });

    Route::post('/', function (Illuminate\Http\Request $request) {
        // 매장명과 지사 정보로 간단하게 추가
        $request->validate([
            'name' => 'required|string|max:255',
            'branch_id' => 'required|exists:branches,id',
            'owner_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        // 자동 코드 생성
        $branch = App\Models\Branch::find($request->branch_id);
        $storeCount = App\Models\Store::where('branch_id', $request->branch_id)->count();
        $autoCode = $branch->code.'-'.str_pad($storeCount + 1, 3, '0', STR_PAD_LEFT);

        $store = App\Models\Store::create([
            'name' => $request->name,
            'code' => $autoCode, // 자동 생성
            'branch_id' => $request->branch_id,
            'owner_name' => $request->owner_name,
            'phone' => $request->phone,
            'address' => '',
            'status' => 'active',
            'opened_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => '매장이 추가되었습니다.',
            'data' => $store->load('branch'),
        ], 201);
    });
    Route::get('/branches', function () {
        // Simplified query to avoid PostgreSQL prepared statement issues
        $branches = App\Models\Branch::select('id', 'name', 'code', 'status')->get();

        // Manually add store count to avoid withCount() issues
        foreach ($branches as $branch) {
            $branch->stores_count = App\Models\Store::where('branch_id', $branch->id)->count();
        }

        return response()->json(['success' => true, 'data' => $branches]);
    });
    Route::post('/sales/save', function (Illuminate\Http\Request $request) {
        try {
            $salesData = $request->input('sales', []);
            $savedCount = 0;

            foreach ($salesData as $sale) {
                App\Models\Sale::create($sale);
                $savedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => $savedCount.'건이 저장되었습니다.',
                'saved_count' => $savedCount,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '저장 오류: '.$e->getMessage(),
            ], 500);
        }
    });
});

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

    // Write operations (POST) - Additional CSRF protection for web requests
    Route::post('/bulk', [SalesApiController::class, 'bulkSave'])
        ->middleware('throttle:30,1') // Rate limiting: 30 requests per minute
        ->name('api.sales.bulk');

    // AgGrid 전용 bulk save 엔드포인트
    Route::post('/bulk-save', [SalesApiController::class, 'bulkSave'])
        ->middleware('throttle:60,1') // Rate limiting: 60 requests per minute
        ->name('api.sales.bulk-save');

    // Bulk delete endpoint for sales data
    Route::post('/bulk-delete', [SalesApiController::class, 'bulkDelete'])
        ->middleware('throttle:30,1') // Rate limiting: 30 requests per minute
        ->name('api.sales.bulk-delete');

    // 개발용 간단한 저장 엔드포인트 (인증 제외)
    Route::post('/save', function (Illuminate\Http\Request $request) {
        try {
            $salesData = $request->input('sales', []);
            $savedCount = 0;
            $errors = [];

            \Log::info('Sales save attempt', [
                'data_count' => count($salesData),
                'first_row' => $salesData[0] ?? null,
                'user_id' => auth()->id(),
            ]);

            $user = auth()->user();

            foreach ($salesData as $index => $sale) {
                // 사용자 정보 또는 기본값 사용
                if (empty($sale['store_id'])) {
                    if ($user && $user->store_id) {
                        $sale['store_id'] = $user->store_id;
                    } else {
                        // 🚨 하드코딩 제거: 매장 정보 없으면 에러
                        throw new Exception('매장 정보가 없는 사용자는 개통표를 저장할 수 없습니다.');
                    }
                }
                if (empty($sale['branch_id'])) {
                    if ($user && $user->branch_id) {
                        $sale['branch_id'] = $user->branch_id;
                    } else {
                        // store_id로부터 branch_id 가져오기
                        $store = App\Models\Store::find($sale['store_id']);
                        if (! $store) {
                            throw new Exception('유효하지 않은 매장 정보입니다.');
                        }
                        $sale['branch_id'] = $store->branch_id;
                    }
                }

                // 빈 문자열을 null로 변환
                foreach ($sale as $key => $value) {
                    if ($value === '') {
                        $sale[$key] = null;
                    }
                }

                try {
                    \Log::info('Attempting to save row '.($index + 1), [
                        'sale_data' => $sale,
                        'missing_fields' => array_keys(array_filter($sale, fn ($v) => is_null($v))),
                    ]);

                    $createdSale = App\Models\Sale::create($sale);
                    $savedCount++;

                    \Log::info('Row '.($index + 1).' saved successfully', ['sale_id' => $createdSale->id]);
                } catch (\Exception $e) {
                    $errorMsg = '행 '.($index + 1).': '.$e->getMessage();
                    $errors[] = $errorMsg;

                    \Log::error('Sale save error for row '.($index + 1), [
                        'error' => $e->getMessage(),
                        'data' => $sale,
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            if ($savedCount > 0) {
                $message = $savedCount.'건이 저장되었습니다.';
                if (! empty($errors)) {
                    $message .= ' (일부 오류: '.count($errors).'건)';
                }

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'saved_count' => $savedCount,
                    'errors' => $errors,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '데이터를 저장할 수 없습니다.',
                    'errors' => $errors,
                ], 400);
            }
        } catch (\Exception $e) {
            \Log::error('Sales save error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => '저장 오류: '.$e->getMessage(),
            ], 500);
        }
    })->withoutMiddleware(['auth', 'rbac'])->name('api.sales.save');
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

/*
|--------------------------------------------------------------------------
| Web API Routes (with CSRF protection)
|--------------------------------------------------------------------------
| These routes are called from the dashboard and require CSRF tokens
*/

// Dashboard stats API (단순 상태 확인용)
Route::get('/api/dashboard/stats', function (Request $request) {
    try {
        return response()->json(['success' => true, 'data' => ['status' => 'active']]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
})->name('api.dashboard.stats');

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

Route::middleware(['web', 'auth'])->prefix('daily-expenses')->group(function () {
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

/*
|--------------------------------------------------------------------------
| 환수금액 관리 API
|--------------------------------------------------------------------------
| 고객 환불 및 통신사 환수 관리
*/

Route::middleware(['web', 'auth'])->prefix('refunds')->group(function () {
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

/*
|--------------------------------------------------------------------------
| 통합 대시보드 API
|--------------------------------------------------------------------------
| 메인 대시보드용 실시간 데이터 제공
*/

// 웹 대시보드용 API (세션 기반 인증)
Route::middleware(['web', 'auth'])->prefix('dashboard')->group(function () {
    // DashboardController를 사용한 권한별 필터링 적용
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

// 기존 익명함수 라우트들 (이후 제거 예정)
Route::prefix('dashboard-old')->group(function () {
    // 대시보드 개요 (통계 페이지 메인) - 통일된 응답 구조
    Route::get('/overview', function () {
        try {
            // 전체/활성 구분된 통계
            $totalStores = \App\Models\Store::count();
            $activeStores = \App\Models\Store::where('status', 'active')->count();
            $totalBranches = \App\Models\Branch::count();
            $activeBranches = \App\Models\Branch::where('status', 'active')->count();
            $totalUsers = \App\Models\User::count();

            // DatabaseHelper를 사용한 실시간 데이터 조회
            $currentYear = now()->year;
            $currentMonth = now()->month;

            $salesActiveStores = DatabaseHelper::executeWithRetry(function () use ($currentYear, $currentMonth) {
                return \App\Models\Sale::whereYear('sale_date', $currentYear)
                    ->whereMonth('sale_date', $currentMonth)
                    ->distinct('store_id')->count();
            });

            $thisMonthSales = DatabaseHelper::safeAggregate(
                'sales',
                'sum',
                'settlement_amount',
                ['sale_date' => [
                    now()->startOfMonth()->toDateTimeString(),
                    now()->endOfMonth()->toDateTimeString(),
                ]]
            );
            // 🔄 실제 목표 API에서 가져오기 (하드코딩 제거)
            $goal = App\Models\Goal::where('target_type', 'system')
                ->where('period_type', 'monthly')
                ->where('is_active', '=', config('database.default') === 'pgsql' ? \DB::raw('true') : true)
                ->whereBetween('period_start', [now()->startOfMonth()->format('Y-m-d'), now()->endOfMonth()->format('Y-m-d')])
                ->first();

            $monthlyTarget = $goal ? $goal->sales_target : config('sales.default_targets.system.monthly_sales');
            $achievementRate = $thisMonthSales > 0 ? round(($thisMonthSales / $monthlyTarget) * 100, 1) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'stores' => [
                        'total' => $totalStores,
                        'active' => $activeStores,
                        'with_sales' => $salesActiveStores,
                    ],
                    'branches' => [
                        'total' => $totalBranches,
                        'active' => $activeBranches,
                    ],
                    'users' => [
                        'total' => $totalUsers,
                        'headquarters' => \App\Models\User::where('role', 'headquarters')->count(),
                        'branch_managers' => \App\Models\User::where('role', 'branch')->count(),
                        'store_staff' => \App\Models\User::where('role', 'store')->count(),
                    ],
                    'this_month_sales' => floatval($thisMonthSales),
                    'achievement_rate' => $achievementRate,
                    'meta' => [
                        'generated_at' => now()->toISOString(),
                        'period' => now()->format('Y-m'),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    })->name('api.dashboard.overview');

    // 매장 랭킹
    Route::get('/store-ranking', function (Illuminate\Http\Request $request) {
        try {
            $limit = min($request->get('limit', 10), 50);
            $rankings = \App\Models\Sale::with(['store', 'store.branch'])
                ->whereMonth('sale_date', now()->month)
                ->select('store_id')
                ->selectRaw('SUM(settlement_amount) as total_sales')
                ->selectRaw('COUNT(*) as activation_count')
                ->groupBy('store_id')
                ->orderBy('total_sales', 'desc')
                ->limit($limit)
                ->get();

            $rankedStores = [];
            foreach ($rankings as $index => $ranking) {
                $store = \App\Models\Store::with('branch')->find($ranking->store_id);
                if ($store) {
                    $rankedStores[] = [
                        'rank' => $index + 1,
                        'store_name' => $store->name,
                        'branch_name' => $store->branch->name ?? '미지정',
                        'total_sales' => floatval($ranking->total_sales),
                        'activation_count' => $ranking->activation_count,
                    ];
                }
            }

            return response()->json(['success' => true, 'data' => $rankedStores]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    })->name('api.dashboard.store-ranking');

    // 재무 요약은 위의 DashboardController::financialSummary 사용
    // 중복 라우트 제거 (458번 라인에서 이미 정의됨)

    // 대리점 성과
    Route::get('/dealer-performance', function (Illuminate\Http\Request $request) {
        try {
            $yearMonth = $request->get('year_month', now()->format('Y-m'));
            [$year, $month] = explode('-', $yearMonth);

            // DatabaseHelper를 사용한 실시간 통신사 성과 데이터 조회
            $performances = DatabaseHelper::executeWithRetry(function () use ($year, $month) {
                return \App\Models\Sale::whereYear('sale_date', $year)
                    ->whereMonth('sale_date', $month)
                    ->select('agency')
                    ->selectRaw('COUNT(*) as count')
                    ->selectRaw('SUM(settlement_amount) as total_amount')
                    ->groupBy('agency')
                    ->get();
            });

            // 통신사별 데이터를 carrier_breakdown 형식으로 변환
            $totalCount = $performances->sum('count');
            $carrierBreakdown = [];

            foreach ($performances as $performance) {
                $percentage = $totalCount > 0 ? round(($performance->count / $totalCount) * 100) : 0;
                $carrierBreakdown[] = [
                    'carrier' => $performance->agency ?? 'Unknown',
                    'count' => (int) $performance->count,
                    'total_sales' => number_format($performance->total_amount, 2),
                    'percentage' => $percentage,
                ];
            }

            $responseData = [
                'carrier_breakdown' => $carrierBreakdown,
                'year_month' => $yearMonth,
            ];

            return response()->json(['success' => true, 'data' => $responseData]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    })->name('api.dashboard.dealer-performance');
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

    // 지사 목록 (통계 페이지용 - 단순화)
    Route::get('/branches', function () {
        try {
            // DatabaseHelper를 사용한 실시간 지사 데이터 조회
            $branches = DatabaseHelper::executeWithRetry(function () {
                return \App\Models\Branch::select('id', 'name', 'code', 'status')->get();
            });

            // 각 지사의 매장 수를 안전하게 조회
            $branchData = [];
            foreach ($branches as $branch) {
                $storeCount = DatabaseHelper::executeWithRetry(function () use ($branch) {
                    return \App\Models\Store::where('branch_id', $branch->id)->count();
                });

                $branchData[] = [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'code' => $branch->code,
                    'users_count' => 0, // 사용자 관계는 현재 사용하지 않음
                    'stores_count' => $storeCount,
                ];
            }

            return response()->json(['success' => true, 'data' => $branchData]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    })->name('api.users.branches');

    // 매장 목록 (특정 지사의 매장들)
    Route::get('/stores', function () {
        try {
            $stores = \App\Models\Store::with('branch')->get();

            return response()->json(['success' => true, 'data' => $stores]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    })->name('api.users.stores');
});

// Profile API (긴급 실배포용 - 인증 제거 버전)
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

/*
|--------------------------------------------------------------------------
| 활동 로그 API (Activity Logging)
|--------------------------------------------------------------------------
| 실시간 사용자 활동 기록 및 조회
*/

Route::middleware(['web', 'auth'])->prefix('activities')->group(function () {
    Route::get('/recent', [App\Http\Controllers\ActivityController::class, 'recent'])->name('api.activities.recent');
    Route::post('/log', [App\Http\Controllers\ActivityController::class, 'log'])->name('api.activities.log');
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
