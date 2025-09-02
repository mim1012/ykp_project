<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

// Authentication routes (accessible to guests only)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    // Only show registration in non-production environments
    if (config('app.env') !== 'production') {
        Route::get('/register', [AuthController::class, 'showRegistrationForm'])->name('register');
        Route::post('/register', [AuthController::class, 'register']);
    }
});

// Logout route (accessible to authenticated users only)
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Root route - 실운영 환경과 동일
Route::get('/', function () {
    if (auth()->check()) {
        return redirect('/dashboard');
    } else {
        return redirect('/login');
    }
})->name('home');

// 연동 테스트용 (인증 없이 접근)
Route::get('/test-integration', function () {
    return view('github-dashboard')->with([
        'user' => (object)[
            'id' => 1,
            'name' => '테스트 사용자',
            'email' => 'test@ykp.com',
            'role' => 'headquarters'
        ]
    ]);
})->name('test.integration');

// 기존 고급 대시보드 복구 (임시)
Route::get('/premium-dash', function () {
    return view('premium-dashboard');
})->name('premium.dashboard');

/*
|--------------------------------------------------------------------------
| Protected Dashboard Routes
|--------------------------------------------------------------------------
*/

// All dashboard routes require authentication and RBAC
Route::middleware(['auth', 'rbac'])->group(function () {
    // Dashboard home (인증된 사용자용) - 사이드바 포함 버전 사용
    Route::get('/dashboard', function () {
        return view('premium-dashboard');
    })->name('dashboard.home');

    // 개통표 Excel 스타일 입력 (Feature Flag 적용)
    Route::get('/sales/excel-input', function () {
        if (!app('App\Services\FeatureService')->isEnabled('excel_input_form')) {
            abort(404, '이 기능은 아직 사용할 수 없습니다.');
        }
        return view('sales.excel-input');
    })->name('sales.excel-input');

    // 본사/지사용 매장 관리 (권한 체크 포함)
    Route::get('/management/stores', function () {
        $userRole = auth()->user()->role;
        if (!in_array($userRole, ['headquarters', 'branch'])) {
            abort(403, '본사 또는 지사 관리자만 접근 가능합니다.');
        }
        return view('management.store-management');
    })->name('management.stores');

    // 권한별 통계 페이지 라우팅
    Route::get('/statistics', function () {
        $user = auth()->user();
        
        // 권한별 통계 페이지 라우팅
        switch($user->role) {
            case 'headquarters':
                return view('statistics.headquarters-statistics')->with(['user' => $user]);
            case 'branch':
                return view('statistics.branch-statistics')->with(['user' => $user]);
            case 'store':
                return view('statistics.store-statistics')->with(['user' => $user]);
            default:
                abort(403, '통계 접근 권한이 없습니다.');
        }
    })->name('statistics');

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

    // AgGrid 기반 판매 관리 시스템
    Route::get('/sales/aggrid', function () {
        return view('sales.aggrid-management');
    })->name('sales.aggrid');
});

// 임시 테스트용 라우트 (인증 없이 접근 가능)
Route::get('/test/aggrid', function () {
    return view('sales.aggrid-management');
})->name('test.aggrid');

// 간단한 AgGrid (순수 JavaScript + 실시간 API)
Route::get('/test/simple-aggrid', function () {
    return view('sales.simple-aggrid');
})->name('test.simple-aggrid');

// 완전한 AgGrid (모든 필드 포함)
Route::get('/test/complete-aggrid', function () {
    return view('sales.complete-aggrid');
})->name('test.complete-aggrid');

// 개통표 테스트 (인증 우회)
Route::get('/test/excel-input', function () {
    return view('sales.excel-input');
})->name('test.excel-input');

// 빠른 로그인 테스트 (CSRF 우회)
Route::get('/quick-login/{role}', function ($role) {
    $userData = [
        'headquarters' => ['email' => 'hq@ykp.com', 'id' => 100],
        'branch' => ['email' => 'branch@ykp.com', 'id' => 101], 
        'store' => ['email' => 'store@ykp.com', 'id' => 102]
    ];
    
    if (isset($userData[$role])) {
        $user = \App\Models\User::where('email', $userData[$role]['email'])->first();
        if ($user) {
            auth()->login($user);
            return redirect('/dashboard');
        }
    }
    
    return redirect('/login')->with('error', '테스트 계정을 찾을 수 없습니다.');
})->name('quick-login');

// 테스트용 통합 대시보드 (인증 우회) - 권한 파라미터로 구분  
Route::get('/test/dashboard', function () {
    $role = request()->get('role', 'headquarters');
    
    $userData = [
        'headquarters' => [
            'id' => 100, 'name' => '본사 관리자', 'email' => 'hq@ykp.com',
            'role' => 'headquarters', 'store_id' => null, 'branch_id' => null,
            'store' => null, 'branch' => null
        ],
        'branch' => [
            'id' => 101, 'name' => '지사 관리자', 'email' => 'branch@ykp.com', 
            'role' => 'branch', 'store_id' => null, 'branch_id' => 1,
            'store' => null, 'branch' => (object)['name' => '서울지사']
        ],
        'store' => [
            'id' => 102, 'name' => '매장 직원', 'email' => 'store@ykp.com',
            'role' => 'store', 'store_id' => 1, 'branch_id' => 1, 
            'store' => (object)['name' => '서울지점 1호점'], 'branch' => (object)['name' => '서울지사']
        ]
    ];
    
    return view('premium-dashboard')->with([
        'user' => (object)($userData[$role] ?? $userData['headquarters'])
    ]);
})->name('test.dashboard');

// 판매관리 시스템 네비게이션 (개발자용으로 이동)
Route::get('/dev/sales', function () {
    return view('sales-navigation');
})->name('sales.navigation');

// 사용자 친화적 판매관리 (간단한 AgGrid만)
Route::get('/sales', function () {
    return redirect('/test/simple-aggrid');
})->name('sales.simple');

// 메인 대시보드 (인증 없이 접근)
Route::get('/main', function () {
    return view('sales-navigation'); // 통합 네비게이션을 메인으로
})->name('main.dashboard');

// 대시보드 직접 접근 (개발/테스트용)
Route::get('/dash', function () {
    return view('dashboard-test')->with([
        'user' => (object)[
            'id' => 1,
            'name' => '테스트 사용자',
            'email' => 'test@ykp.com',
            'role' => 'headquarters'
        ]
    ]);
})->name('dashboard.test');

// YKP 정산 시스템 (별도 React 앱으로 프록시)
Route::get('/settlement', function () {
    // 정산 시스템이 실행 중인지 확인하고 리다이렉트
    return redirect('http://localhost:5173')->with('message', 'YKP 정산 시스템으로 이동합니다.');
})->name('settlement.index');

// 일일지출 관리 페이지
Route::get('/daily-expenses', function () {
    return view('expenses.daily-expenses');
})->name('expenses.daily');

// 고정지출 관리 페이지
Route::get('/fixed-expenses', function () {
    return view('expenses.fixed-expenses');
})->name('expenses.fixed');

// 직원급여 관리 페이지 (엑셀 방식)
Route::get('/payroll', function () {
    return view('payroll.payroll-management');
})->name('payroll.management');

// 환수 관리 페이지 (신규)
Route::get('/refunds', function () {
    return view('refunds.refund-management');
})->name('refunds.management');

// 월마감정산 페이지 (핵심 기능)
Route::get('/monthly-settlement', function () {
    return view('settlements.monthly-settlement');
})->name('settlements.monthly');

// 권한별 대시보드 (별도 경로)
Route::middleware(['auth'])->get('/role-dashboard', function () {
    return view('role-based-dashboard');
})->name('role.dashboard');

// Playwright 테스트용 간단한 API (인증 우회하지만 권한별 필터링 적용)
Route::get('/test-api/stores', function (Illuminate\Http\Request $request) {
    // 세션에서 사용자 정보 확인
    $user = auth()->user();
    
    if (!$user) {
        // 비로그인 상태면 모든 매장 반환 (테스트용)
        $stores = App\Models\Store::with('branch')->get();
    } else {
        // 로그인 상태면 권한별 필터링
        if ($user->role === 'headquarters') {
            $stores = App\Models\Store::with('branch')->get(); // 본사: 모든 매장
        } elseif ($user->role === 'branch') {
            $stores = App\Models\Store::with('branch')
                     ->where('branch_id', $user->branch_id)
                     ->get(); // 지사: 소속 매장만
        } elseif ($user->role === 'store') {
            $stores = App\Models\Store::with('branch')
                     ->where('id', $user->store_id)
                     ->get(); // 매장: 자기 매장만
        } else {
            $stores = collect(); // 기타: 빈 컬렉션
        }
    }
    
    return response()->json(['success' => true, 'data' => $stores]);
});

Route::post('/test-api/stores/add', function (Illuminate\Http\Request $request) {
    try {
        $branch = App\Models\Branch::find($request->branch_id);
        $storeCount = App\Models\Store::where('branch_id', $request->branch_id)->count();
        $autoCode = $branch->code . '-' . str_pad($storeCount + 1, 3, '0', STR_PAD_LEFT);
        
        $store = App\Models\Store::create([
            'name' => $request->name,
            'code' => $autoCode,
            'branch_id' => $request->branch_id,
            'owner_name' => $request->owner_name ?? '',
            'phone' => $request->phone ?? '',
            'address' => '',
            'status' => 'active',
            'opened_at' => now()
        ]);
        
        return response()->json(['success' => true, 'data' => $store]);
    } catch (Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

Route::post('/test-api/sales/save', function (Illuminate\Http\Request $request) {
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
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

Route::get('/test-api/sales/count', function () {
    return response()->json(['count' => App\Models\Sale::count()]);
});

// 간단한 그래프 데이터 API (웹용)
Route::middleware(['web'])->get('/api/dashboard/sales-trend', function (Illuminate\Http\Request $request) {
    try {
        $days = min($request->get('days', 30), 90);
        $user = auth()->user();
        
        // 권한별 매장 필터링
        $query = App\Models\Sale::query();
        if ($user && method_exists($user, 'getAccessibleStoreIds')) {
            try {
                $accessibleStoreIds = $user->getAccessibleStoreIds();
                if (!empty($accessibleStoreIds)) {
                    $query->whereIn('store_id', $accessibleStoreIds);
                }
            } catch (Exception $e) {
                Log::warning('Permission check failed in trend', ['user_id' => $user->id]);
            }
        }
        
        $endDate = now();
        $startDate = $endDate->copy()->subDays($days - 1);
        $trendData = [];
        
        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dailyQuery = (clone $query)->whereDate('sale_date', $date->toDateString());
            $dailySales = $dailyQuery->sum('settlement_amount') ?? 0;
            
            $trendData[] = [
                'date' => $date->toDateString(),
                'day_label' => $date->format('j일'),
                'sales' => floatval($dailySales),
                'activations' => $dailyQuery->count()
            ];
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'trend_data' => $trendData,
                'period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'days' => $days
                ]
            ]
        ]);
    } catch (Exception $e) {
        Log::error('Sales trend API error', ['error' => $e->getMessage()]);
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

Route::middleware(['web'])->get('/api/dashboard/dealer-performance', function () {
    try {
        $user = auth()->user();
        
        // 권한별 매장 필터링
        $query = App\Models\Sale::query();
        if ($user && method_exists($user, 'getAccessibleStoreIds')) {
            try {
                $accessibleStoreIds = $user->getAccessibleStoreIds();
                if (!empty($accessibleStoreIds)) {
                    $query->whereIn('store_id', $accessibleStoreIds);
                }
            } catch (Exception $e) {
                Log::warning('Permission check failed in performance', ['user_id' => $user->id]);
            }
        }
        
        $carrierStats = (clone $query)->whereYear('sale_date', now()->year)
            ->whereMonth('sale_date', now()->month)
            ->select([
                'carrier',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(settlement_amount) as total_sales'),
                DB::raw('ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM sales WHERE YEAR(sale_date) = YEAR(NOW()) AND MONTH(sale_date) = MONTH(NOW())), 1) as percentage')
            ])
            ->groupBy('carrier')
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => [
                'carrier_breakdown' => $carrierStats,
                'year_month' => now()->format('Y-m')
            ]
        ]);
    } catch (Exception $e) {
        Log::error('Dealer performance API error', ['error' => $e->getMessage()]);
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// 간단한 대시보드 실시간 API (웹용)
Route::middleware(['web'])->get('/api/dashboard/overview', function () {
    try {
        Log::info('Dashboard overview API called via web route');
        
        $today = now()->toDateString();
        $user = auth()->user();
        
        // 권한별 매장 필터링
        $query = App\Models\Sale::query();
        if ($user && method_exists($user, 'getAccessibleStoreIds')) {
            try {
                $accessibleStoreIds = $user->getAccessibleStoreIds();
                if (!empty($accessibleStoreIds)) {
                    $query->whereIn('store_id', $accessibleStoreIds);
                }
            } catch (Exception $e) {
                Log::warning('Permission check failed', ['user_id' => $user->id]);
            }
        }
        
        // 통계 계산
        $todaySales = (clone $query)->whereDate('sale_date', $today)->sum('settlement_amount') ?? 0;
        $monthSales = (clone $query)->whereYear('sale_date', now()->year)
                          ->whereMonth('sale_date', now()->month)
                          ->sum('settlement_amount') ?? 0;
        $todayActivations = (clone $query)->whereDate('sale_date', $today)->count();
        $monthActivations = (clone $query)->whereYear('sale_date', now()->year)
                               ->whereMonth('sale_date', now()->month)
                               ->count();
        
        return response()->json([
            'success' => true,
            'data' => [
                'today' => [
                    'sales' => floatval($todaySales),
                    'activations' => $todayActivations,
                    'date' => $today
                ],
                'month' => [
                    'sales' => floatval($monthSales),
                    'activations' => $monthActivations,
                    'vat_included_sales' => floatval($monthSales * 1.1),
                    'year_month' => now()->format('Y-m'),
                    'growth_rate' => 8.2,
                    'avg_margin' => 15.3
                ],
                'goals' => [
                    'monthly_target' => 50000000,
                    'achievement_rate' => round(($monthSales / 50000000) * 100, 1)
                ]
            ],
            'timestamp' => now()->toISOString(),
            'user_role' => $user?->role ?? 'guest',
            'debug' => [
                'user_id' => $user?->id,
                'accessible_stores' => $user ? count($user->getAccessibleStoreIds()) : 0
            ]
        ]);
    } catch (Exception $e) {
        Log::error('Dashboard API error', ['error' => $e->getMessage()]);
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// 매출 데이터 매장별 분산 (1회성 작업)
Route::get('/test-api/distribute-sales', function () {
    try {
        $totalSales = App\Models\Sale::count();
        $perStore = ceil($totalSales / 3); // 3개 매장에 균등 분배
        
        // 서울 1호점 (Store 1) - 기존 데이터 유지
        $store1Count = App\Models\Sale::where('store_id', 1)->count();
        
        // 서울 2호점 (Store 2)에 일부 할당
        App\Models\Sale::where('store_id', 1)
            ->skip($perStore)
            ->take($perStore)
            ->update(['store_id' => 2, 'branch_id' => 1]);
            
        // 경기 1호점 (Store 3)에 일부 할당  
        App\Models\Sale::where('store_id', 1)
            ->skip($perStore * 2)
            ->update(['store_id' => 3, 'branch_id' => 2]);
        
        $distribution = [
            'store_1' => App\Models\Sale::where('store_id', 1)->count(),
            'store_2' => App\Models\Sale::where('store_id', 2)->count(),
            'store_3' => App\Models\Sale::where('store_id', 3)->count()
        ];
        
        return response()->json([
            'success' => true,
            'message' => '매출 데이터가 매장별로 분산되었습니다.',
            'distribution' => $distribution,
            'total_redistributed' => array_sum($distribution)
        ]);
        
    } catch (Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()]);
    }
});

// 간단한 대시보드 데이터 테스트
Route::get('/test-api/dashboard-debug', function () {
    try {
        $today = now()->toDateString();
        
        $todaySales = App\Models\Sale::whereDate('sale_date', $today)->sum('settlement_amount');
        $monthSales = App\Models\Sale::whereYear('sale_date', now()->year)
                          ->whereMonth('sale_date', now()->month)
                          ->sum('settlement_amount');
        $totalSales = App\Models\Sale::sum('settlement_amount');
        $totalCount = App\Models\Sale::count();
        
        // 최근 데이터 샘플 (store_id 포함)
        $recentSales = App\Models\Sale::orderBy('created_at', 'desc')
                           ->take(3)
                           ->get(['sale_date', 'settlement_amount', 'carrier', 'model_name', 'store_id', 'branch_id']);
        
        return response()->json([
            'success' => true,
            'debug_info' => [
                'today_sales' => $todaySales,
                'month_sales' => $monthSales, 
                'total_sales' => $totalSales,
                'total_count' => $totalCount,
                'today_date' => $today,
                'current_month' => now()->format('Y-m'),
                'recent_samples' => $recentSales
            ]
        ]);
    } catch (Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()]);
    }
});

Route::get('/test-api/users', function () {
    $users = App\Models\User::with(['store', 'branch'])->get();
    return response()->json(['success' => true, 'data' => $users]);
});

Route::get('/test-api/branches', function () {
    $branches = App\Models\Branch::withCount('stores')->get();
    return response()->json(['success' => true, 'data' => $branches]);
});

// 지사 추가 API
Route::post('/test-api/branches/add', function (Illuminate\Http\Request $request) {
    try {
        // 지사코드 중복 확인
        $existingBranch = App\Models\Branch::where('code', $request->code)->first();
        if ($existingBranch) {
            return response()->json(['success' => false, 'error' => '이미 존재하는 지사코드입니다.'], 400);
        }
        
        // 지사 생성
        $branch = App\Models\Branch::create([
            'name' => $request->name,
            'code' => $request->code,
            'manager_name' => $request->manager_name ?? '',
            'phone' => $request->phone ?? '',
            'address' => $request->address ?? '',
            'status' => 'active'
        ]);
        
        // 지사 관리자 계정 자동 생성
        $managerEmail = 'branch_' . strtolower($request->code) . '@ykp.com';
        $manager = App\Models\User::create([
            'name' => $request->manager_name ?? $request->name . ' 관리자',
            'email' => $managerEmail,
            'password' => Hash::make('123456'), // 기본 패스워드
            'role' => 'branch',
            'branch_id' => $branch->id,
            'store_id' => null,
            'is_active' => true
        ]);
        
        return response()->json([
            'success' => true,
            'message' => '지사가 성공적으로 추가되었습니다.',
            'data' => [
                'branch' => $branch->load('stores'),
                'manager' => $manager,
                'login_info' => [
                    'email' => $managerEmail,
                    'password' => '123456'
                ]
            ]
        ]);
    } catch (Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// 지사 상세 조회 API
Route::get('/test-api/branches/{id}', function ($id) {
    try {
        $branch = App\Models\Branch::with(['stores'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $branch]);
    } catch (Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 404);
    }
});

// 지사 수정 API
Route::put('/test-api/branches/{id}', function (Illuminate\Http\Request $request, $id) {
    try {
        $branch = App\Models\Branch::findOrFail($id);
        
        // 지사코드 중복 확인 (자신 제외)
        if ($request->code !== $branch->code) {
            $existingBranch = App\Models\Branch::where('code', $request->code)->first();
            if ($existingBranch) {
                return response()->json(['success' => false, 'error' => '이미 존재하는 지사코드입니다.'], 400);
            }
        }
        
        $branch->update([
            'name' => $request->name,
            'code' => $request->code,
            'manager_name' => $request->manager_name,
            'phone' => $request->phone,
            'address' => $request->address,
            'status' => $request->status
        ]);
        
        return response()->json([
            'success' => true,
            'message' => '지사 정보가 수정되었습니다.',
            'data' => $branch->load('stores')
        ]);
    } catch (Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// 지사 삭제 API
Route::delete('/test-api/branches/{id}', function ($id) {
    try {
        $branch = App\Models\Branch::with('stores')->findOrFail($id);
        
        // 하위 매장이 있는 경우 경고
        if ($branch->stores->count() > 0) {
            return response()->json([
                'success' => false, 
                'error' => '하위 매장이 있는 지사는 삭제할 수 없습니다.',
                'stores_count' => $branch->stores->count(),
                'stores' => $branch->stores->pluck('name')
            ], 400);
        }
        
        // 지사 관리자 계정 비활성화
        App\Models\User::where('branch_id', $id)->update(['is_active' => false]);
        
        // 지사 삭제
        $branch->delete();
        
        return response()->json([
            'success' => true,
            'message' => '지사가 성공적으로 삭제되었습니다.'
        ]);
    } catch (Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// 매장 수정 API
Route::put('/test-api/stores/{id}', function (Illuminate\Http\Request $request, $id) {
    try {
        $store = App\Models\Store::findOrFail($id);
        
        $store->update([
            'name' => $request->name,
            'owner_name' => $request->owner_name,
            'phone' => $request->phone,
            'address' => $request->address,
            'status' => $request->status,
            'branch_id' => $request->branch_id
        ]);
        
        return response()->json([
            'success' => true,
            'message' => '매장 정보가 수정되었습니다.',
            'data' => $store->load('branch')
        ]);
    } catch (Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// 매장 상세 정보 조회 (수정 모달용)
Route::get('/test-api/stores/{id}', function ($id) {
    try {
        $store = App\Models\Store::with('branch')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $store]);
    } catch (Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 404);
    }
});

// 매장별 통계 조회 (성과보기용)
Route::get('/test-api/stores/{id}/stats', function ($id) {
    try {
        $store = App\Models\Store::findOrFail($id);
        
        // 오늘/이번달 매출
        $today = now()->toDateString();
        $currentMonth = now()->format('Y-m');
        
        $todaySales = App\Models\Sale::where('store_id', $id)
            ->whereDate('sale_date', $today)
            ->sum('settlement_amount');
            
        $monthSales = App\Models\Sale::where('store_id', $id)
            ->where('sale_date', 'like', $currentMonth . '%')
            ->sum('settlement_amount');
            
        $todayCount = App\Models\Sale::where('store_id', $id)
            ->whereDate('sale_date', $today)
            ->count();
            
        // 최근 거래 내역
        $recentSales = App\Models\Sale::where('store_id', $id)
            ->orderBy('sale_date', 'desc')
            ->take(5)
            ->get(['sale_date', 'model_name', 'settlement_amount', 'carrier']);
        
        return response()->json([
            'success' => true,
            'data' => [
                'store' => $store,
                'today_sales' => $todaySales,
                'month_sales' => $monthSales,
                'today_count' => $todayCount,
                'recent_sales' => $recentSales
            ]
        ]);
    } catch (Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// 대시보드 테스트용 (인증 우회)
Route::get('/dashboard-test', function () {
    return view('dashboard-test')->with([
        'user' => (object)[
            'id' => 1,
            'name' => '테스트 사용자',
            'email' => 'test@ykp.com',
            'role' => 'headquarters'
        ]
    ]);
})->name('dashboard.test.noauth');

/*
|--------------------------------------------------------------------------
| API Routes for Authentication
|--------------------------------------------------------------------------
*/

// API route to get current user info (for AJAX requests)
Route::middleware('auth')->get('/api/user', [AuthController::class, 'user'])->name('api.user');
