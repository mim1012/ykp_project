<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    /**
     * 대시보드 개요 데이터 (통계 페이지 메인)
     */
    public function overview(Request $request)
    {
        try {
            $user = auth()->user();

            // 캐시 키 생성 (5분 단위로 반올림하여 같은 시간대 사용자들이 같은 캐시 공유)
            $cacheKey = sprintf(
                'dashboard_overview_%s_%s_%s',
                $user->role,
                $user->id,
                now()->format('Y-m-d-H:i') // 분 단위
            );
            // 5분 단위로 반올림 (14:37 → 14:35)
            $cacheKey = substr($cacheKey, 0, -1) . floor(now()->minute / 5) * 5;

            // 5분간 캐시 유지 (300초)
            return Cache::remember($cacheKey, 300, function () use ($user, $request, $cacheKey) {
                Log::info('Dashboard overview API called (cache miss)', ['user_id' => $user->id]);

            // 사용자 정보 상세 로깅
            Log::info('Dashboard user details', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_role' => $user->role,
                'store_id' => $user->store_id,
                'branch_id' => $user->branch_id,
            ]);

            // 권한별 쿼리 스코프 설정
            $storeQuery = Store::query();
            $branchQuery = Branch::query();
            $userQuery = User::query();
            $saleQuery = Sale::query();

            // 지사 계정: 자기 지사 데이터만
            if ($user->isBranch()) {
                $storeQuery->where('branch_id', $user->branch_id);
                $branchQuery->where('id', $user->branch_id);
                $userQuery->where('branch_id', $user->branch_id);
                $saleQuery->where('branch_id', $user->branch_id);
            }
            // 매장 계정: 자기 매장 데이터만
            elseif ($user->isStore()) {
                $storeQuery->where('id', $user->store_id);
                $branchQuery->where('id', $user->branch_id);
                $userQuery->where('store_id', $user->store_id);
                $saleQuery->where('store_id', $user->store_id);
            }
            // 본사: 전체 데이터 (필터링 없음)

            // 전체/활성 구분된 통계 (안전한 clone 사용)
            $totalStores = (clone $storeQuery)->count();
            $activeStores = (clone $storeQuery)->where('status', 'active')->count();
            $totalBranches = (clone $branchQuery)->count();
            $activeBranches = (clone $branchQuery)->where('status', 'active')->count();
            $totalUsers = (clone $userQuery)->count();
            // PostgreSQL 호환 boolean 쿼리
            if (config('database.default') === 'pgsql') {
                $activeUsers = (clone $userQuery)->whereRaw('is_active = true')->count();
            } else {
                $activeUsers = (clone $userQuery)->where('is_active', true)->count();
            }

            // 날짜 범위 계산 - DB 독립적인 방법 사용
            $startOfMonth = now()->startOfMonth()->format('Y-m-d');
            $endOfMonth = now()->endOfMonth()->format('Y-m-d');
            $today = now()->format('Y-m-d');

            // 매출 데이터가 있는 매장 수 (실제 활동 매장)
            $salesActiveStores = (clone $saleQuery)
                ->whereBetween('sale_date', [$startOfMonth, $endOfMonth])
                ->distinct('store_id')
                ->count();

            // 이번달 매출 (실제 데이터)
            $thisMonthSales = (clone $saleQuery)
                ->whereBetween('sale_date', [$startOfMonth, $endOfMonth])
                ->sum('settlement_amount');

            // 오늘 개통 건수 및 매출액
            $todayActivations = (clone $saleQuery)->whereDate('sale_date', $today)->count();
            $todaySalesAmount = (clone $saleQuery)->whereDate('sale_date', $today)->sum('settlement_amount');

            // 디버깅: 실제 매출 데이터 확인
            $debugInfo = [
                'user_role' => $user->role,
                'user_branch_id' => $user->branch_id,
                'user_store_id' => $user->store_id,
                'date_range' => [$startOfMonth, $endOfMonth],
                'today' => $today,
                'total_sales_count' => (clone $saleQuery)->count(),
                'this_month_sales_count' => (clone $saleQuery)->whereBetween('sale_date', [$startOfMonth, $endOfMonth])->count(),
                'this_month_sales_amount' => $thisMonthSales,
                'today_activations' => $todayActivations,
                'today_sales_amount' => $todaySalesAmount,
            ];

            // 지사 계정인 경우 실제 sales 테이블의 branch_id 확인
            if ($user->isBranch()) {
                $salesBranchIds = Sale::distinct('branch_id')->pluck('branch_id')->toArray();
                $debugInfo['sales_table_branch_ids'] = $salesBranchIds;
                $debugInfo['user_branch_matches_sales'] = in_array($user->branch_id, $salesBranchIds);

                // 해당 지사의 매장들 확인
                $branchStoreIds = Store::where('branch_id', $user->branch_id)->pluck('id')->toArray();
                $debugInfo['branch_store_ids'] = $branchStoreIds;

                // Sales 테이블에서 해당 매장들의 데이터 직접 확인
                $directSalesCount = Sale::whereIn('store_id', $branchStoreIds)
                    ->whereBetween('sale_date', [$startOfMonth, $endOfMonth])
                    ->count();
                $directSalesSum = Sale::whereIn('store_id', $branchStoreIds)
                    ->whereBetween('sale_date', [$startOfMonth, $endOfMonth])
                    ->sum('settlement_amount');

                $debugInfo['direct_sales_count_by_store'] = $directSalesCount;
                $debugInfo['direct_sales_sum_by_store'] = $directSalesSum;
            }

            // 매장 계정인 경우 추가 디버그 정보
            if ($user->isStore()) {
                $storeSales = Sale::where('store_id', $user->store_id)->get();
                $debugInfo['store_sales_records'] = $storeSales->map(function ($sale) {
                    return [
                        'id' => $sale->id,
                        'sale_date' => $sale->sale_date,
                        'settlement_amount' => $sale->settlement_amount,
                        'store_id' => $sale->store_id,
                    ];
                });
                $debugInfo['store_sales_today_query'] = Sale::where('store_id', $user->store_id)
                    ->whereDate('sale_date', $today)->toSql();
                $debugInfo['store_sales_month_query'] = Sale::where('store_id', $user->store_id)
                    ->whereBetween('sale_date', [$startOfMonth, $endOfMonth])->toSql();
            }

            Log::info('Dashboard Sales Query Debug', $debugInfo);

            // 목표 달성률은 Goals 테이블에 데이터가 있을 때만 계산
            $monthlyTarget = config('sales.default_targets.system.monthly_sales', 50000000);
            $achievementRate = $thisMonthSales > 0 && $monthlyTarget > 0 ? round(($thisMonthSales / $monthlyTarget) * 100, 1) : 0;

            Log::info('Dashboard overview calculated', [
                'total_stores' => $totalStores,
                'active_stores' => $activeStores,
                'sales_active_stores' => $salesActiveStores,
                'total_branches' => $totalBranches,
                'active_branches' => $activeBranches,
                'this_month_sales' => $thisMonthSales,
                'achievement_rate' => $achievementRate,
            ]);

            $responseData = [
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
                        'active' => $activeUsers,
                        'headquarters' => (clone $userQuery)->where('role', 'headquarters')->count(),
                        'branch_managers' => (clone $userQuery)->where('role', 'branch')->count(),
                        'store_staff' => (clone $userQuery)->where('role', 'store')->count(),
                    ],
                    'this_month_sales' => floatval($thisMonthSales),
                    'today_activations' => $todayActivations,
                    'today_sales' => floatval($todaySalesAmount),
                    'monthly_target' => $monthlyTarget,
                    'achievement_rate' => $achievementRate,
                    'currency' => 'KRW',
                    'meta' => [
                        'generated_at' => now()->toISOString(),
                        'period' => now()->format('Y-m'),
                        'user_branch_id' => $user->branch_id,
                        'user_store_id' => $user->store_id,
                    ],
                ],
            ];

            // 개발 환경에서만 디버그 정보 추가
            if (config('app.debug')) {
                $responseData['debug'] = $debugInfo;
            }

                // 캐시 생성 시간 메타데이터 추가
                $responseData['data']['meta']['cached_at'] = now()->toISOString();
                $responseData['data']['meta']['cache_key'] = $cacheKey;

                return response()->json($responseData);
            }); // Cache::remember 종료

        } catch (\Exception $e) {
            Log::error('Dashboard overview API error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'error' => '대시보드 데이터 로딩 중 오류가 발생했습니다.',
            ], 500);
        }
    }

    /**
     * 매장별 랭킹 데이터
     */
    public function storeRanking(Request $request)
    {
        try {
            $user = auth()->user();
            $period = $request->get('period', 'monthly');
            $limit = min($request->get('limit', 10), 50);

            // 캐시 키 생성 (기간별, 권한별로 다른 캐시)
            $cacheKey = sprintf(
                'store_ranking_%s_%s_%s_%s_%s',
                $user->role,
                $user->id,
                $period,
                $limit,
                now()->format('Y-m-d-H:i')
            );
            // 5분 단위로 반올림
            $cacheKey = substr($cacheKey, 0, -1) . floor(now()->minute / 5) * 5;

            return Cache::remember($cacheKey, 300, function () use ($user, $period, $limit, $request) {

            // 기간별 필터링
            $query = Sale::with(['store', 'store.branch']);

            // RBAC 필터링: 지사는 소속 매장만, 매장은 자기 매장만
            if ($user->role === 'branch' && $user->branch_id) {
                $query->where('branch_id', $user->branch_id);
            } elseif ($user->role === 'store' && $user->store_id) {
                $query->where('store_id', $user->store_id);
            }
            // headquarters는 모든 매장 조회 가능

            switch ($period) {
                case 'daily':
                    $query->whereDate('sale_date', today());
                    break;
                case 'weekly':
                    $query->whereBetween('sale_date', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'monthly':
                default:
                    $startOfMonth = now()->startOfMonth();
                    $endOfMonth = now()->endOfMonth();
                    $query->whereBetween('sale_date', [$startOfMonth, $endOfMonth]);
                    break;
            }

            // 매장별 매출 집계
            $rankings = $query->select('store_id')
                ->selectRaw('SUM(settlement_amount) as total_sales')
                ->selectRaw('COUNT(*) as activation_count')
                ->groupBy('store_id')
                ->orderBy('total_sales', 'desc')
                ->limit($limit)
                ->get();

            // 매장 정보를 한 번에 로드 (N+1 방지)
            $storeIds = $rankings->pluck('store_id')->toArray();
            $stores = Store::with('branch')->whereIn('id', $storeIds)->get()->keyBy('id');

            // 매장 정보 매핑
            $rankedStores = [];
            foreach ($rankings as $index => $ranking) {
                $store = $stores->get($ranking->store_id);
                if ($store) {
                    $rankedStores[] = [
                        'rank' => $index + 1,
                        'store_id' => $store->id,
                        'store_name' => $store->name,
                        'branch_name' => $store->branch->name ?? '미지정',
                        'total_sales' => floatval($ranking->total_sales),
                        'activation_count' => $ranking->activation_count,
                        'target_achievement' => $this->calculateStoreTargetAchievement($store->id, floatval($ranking->total_sales)),
                    ];
                }
            }

                return response()->json([
                    'success' => true,
                    'data' => $rankedStores,
                    'meta' => [
                        'period' => $period,
                        'total_stores_with_sales' => count($rankedStores),
                        'cached_at' => now()->toISOString(),
                        'generated_at' => now()->toISOString(),
                    ],
                ]);
            }); // Cache::remember 종료

        } catch (\Exception $e) {
            Log::error('Store ranking API error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => '매장 랭킹 데이터 로딩 오류',
            ], 500);
        }
    }

    /**
     * 재무 요약 데이터
     */
    public function financialSummary(Request $request)
    {
        try {
            $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
            $endDate = $request->get('end_date', now()->endOfMonth()->toDateString());
            $user = auth()->user();

            // 캐시 키 생성 (날짜 범위별로 다른 캐시)
            $cacheKey = sprintf(
                'financial_summary_%s_%s_%s_%s_%s',
                $user->role,
                $user->id,
                $startDate,
                $endDate,
                now()->format('Y-m-d-H:i')
            );
            // 5분 단위로 반올림
            $cacheKey = substr($cacheKey, 0, -1) . floor(now()->minute / 5) * 5;

            return Cache::remember($cacheKey, 300, function () use ($user, $startDate, $endDate, $request) {

            // 권한에 따른 필터링 추가
            $salesQuery = Sale::whereBetween('sale_date', [$startDate, $endDate]);

            // 본사: 전체 데이터
            // 지사: 해당 지사 데이터만
            // 매장: 해당 매장 데이터만
            if ($user->isBranch()) {
                $salesQuery->where('branch_id', $user->branch_id);
            } elseif ($user->isStore()) {
                $salesQuery->where('store_id', $user->store_id);
            }

            // 실제 DB 컬럼명 사용
            $totalSales = (clone $salesQuery)->sum('settlement_amount');
            $totalActivations = (clone $salesQuery)->count();
            // 세금 제거 (커밋 427845b6): 마진 = 정산금
            $totalMargin = (clone $salesQuery)->sum('settlement_amount');

            // 마진율 계산
            $averageMarginRate = $totalSales > 0 ? round(($totalMargin / $totalSales) * 100, 1) : 0;

                return response()->json([
                    'success' => true,
                    'data' => [
                        'total_sales' => floatval($totalSales),
                        'total_activations' => $totalActivations,
                        'total_margin' => floatval($totalMargin),
                        'average_margin_rate' => $averageMarginRate,
                        'period' => [
                            'start' => $startDate,
                            'end' => $endDate,
                        ],
                    ],
                    'meta' => [
                        'cached_at' => now()->toISOString(),
                        'generated_at' => now()->toISOString(),
                    ],
                ]);
            }); // Cache::remember 종료

        } catch (\Exception $e) {
            Log::error('Financial summary API error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'error' => '재무 요약 데이터 로딩 중 오류가 발생했습니다.'], 500);
        }
    }

    /**
     * 대리점별 성과 데이터
     */
    public function dealerPerformance(Request $request)
    {
        try {
            $yearMonth = $request->get('year_month', now()->format('Y-m'));
            $user = auth()->user();

            // 캐시 키 생성
            $cacheKey = sprintf(
                'dealer_performance_%s_%s_%s_%s',
                $user->role,
                $user->id,
                $yearMonth,
                now()->format('Y-m-d-H:i')
            );
            // 5분 단위로 반올림
            $cacheKey = substr($cacheKey, 0, -1) . floor(now()->minute / 5) * 5;

            return Cache::remember($cacheKey, 300, function () use ($user, $yearMonth, $request) {

            [$year, $month] = explode('-', $yearMonth);

            // 날짜 범위 계산
            $startDate = date('Y-m-d', mktime(0, 0, 0, $month, 1, $year));
            $endDate = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year)); // 해당 월의 마지막 날

            $query = Sale::with(['store', 'store.branch'])
                ->whereBetween('sale_date', [$startDate, $endDate]);

            // 권한별 필터링
            if ($user->isBranch()) {
                $query->where('branch_id', $user->branch_id);
            } elseif ($user->isStore()) {
                $query->where('store_id', $user->store_id);
            }

            $performances = $query->select('agency')
                ->selectRaw('COUNT(*) as count')
                ->selectRaw('SUM(settlement_amount) as total_amount')
                ->groupBy('agency')
                ->orderBy('total_amount', 'desc')
                ->get();

            $data = $performances->map(function ($perf) {
                return [
                    'agency_name' => $perf->agency,
                    'activation_count' => $perf->count,
                    'total_sales' => floatval($perf->total_amount),
                    'market_share' => 0, // 계산 로직 추가 필요
                ];
            });

                return response()->json([
                    'success' => true,
                    'data' => $data,
                    'meta' => [
                        'year_month' => $yearMonth,
                        'cached_at' => now()->toISOString(),
                        'generated_at' => now()->toISOString(),
                    ],
                ]);
            }); // Cache::remember 종료

        } catch (\Exception $e) {
            Log::error('Dealer performance API error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 지사/매장 순위 데이터 (권한별 필터링)
     */
    public function rankings(Request $request)
    {
        try {
            $user = auth()->user();

            // 캐시 키 생성
            $cacheKey = sprintf(
                'rankings_%s_%s_%s',
                $user->role,
                $user->id,
                now()->format('Y-m-d-H:i')
            );
            // 5분 단위로 반올림
            $cacheKey = substr($cacheKey, 0, -1) . floor(now()->minute / 5) * 5;

            return Cache::remember($cacheKey, 300, function () use ($user, $request) {

            // 이번 달 기준 매출 데이터
            $startOfMonth = now()->startOfMonth();
            $endOfMonth = now()->endOfMonth();
            $salesQuery = Sale::whereBetween('sale_date', [$startOfMonth, $endOfMonth]);

            // 1. 지사 순위 계산
            $branchRankings = $salesQuery->clone()
                ->select('branch_id', DB::raw('SUM(settlement_amount) as total'))
                ->groupBy('branch_id')
                ->orderByDesc('total')
                ->get();

            $branchRank = null;
            $branchTotal = $branchRankings->count();

            if ($user->branch_id) {
                $branchRank = $branchRankings->search(function ($ranking) use ($user) {
                    return $ranking->branch_id == $user->branch_id;
                }) + 1;

                if ($branchRank === 0) {
                    $branchRank = null;
                } // 데이터 없으면 null
            }

            // 2. 매장 순위 계산 (전국 전체 매장 기준)
            $storeQuery = $salesQuery->clone()
                ->select('store_id', DB::raw('SUM(settlement_amount) as total'));

            // 🔥 수정: 전국 전체 매장 중 순위 계산 (필터링 제거)
            // 모든 계정이 전국 순위를 볼 수 있도록 변경

            $storeRankings = $storeQuery->groupBy('store_id')
                ->orderByDesc('total')
                ->get();

            $storeRank = null;
            $storeTotal = $storeRankings->count();

            if ($user->store_id) {
                $storeRank = $storeRankings->search(function ($ranking) use ($user) {
                    return $ranking->store_id == $user->store_id;
                }) + 1;

                if ($storeRank === 0) {
                    $storeRank = null;
                } // 데이터 없으면 null
            }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'branch' => [
                            'rank' => $branchRank,
                            'total' => $branchTotal,
                            'user_branch_id' => $user->branch_id,
                        ],
                        'store' => [
                            'rank' => $storeRank,
                            'total' => $storeTotal,
                            'user_store_id' => $user->store_id,
                            'scope' => 'nationwide', // 🔥 수정: 항상 전국 순위
                        ],
                    ],
                    'meta' => [
                        'user_role' => $user->role,
                        'period' => now()->format('Y-m'),
                        'cached_at' => now()->toISOString(),
                        'generated_at' => now()->toISOString(),
                    ],
                ]);
            }); // Cache::remember 종료

        } catch (\Exception $e) {
            Log::error('Rankings API error', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * TOP N 지사/매장 리스트 (권한별 필터링)
     */
    public function topList(Request $request)
    {
        try {
            $type = $request->query('type', 'store'); // branch|store
            $limit = min($request->query('limit', 5), 20); // 최대 20개
            $period = $request->query('period', 'this_month'); // this_month|last_month|last_3_months
            $user = auth()->user();

            // 캐시 키 생성
            $cacheKey = sprintf(
                'top_list_%s_%s_%s_%s_%s_%s',
                $user->role,
                $user->id,
                $type,
                $limit,
                $period,
                now()->format('Y-m-d-H:i')
            );
            // 5분 단위로 반올림
            $cacheKey = substr($cacheKey, 0, -1) . floor(now()->minute / 5) * 5;

            return Cache::remember($cacheKey, 300, function () use ($user, $type, $limit, $period, $request) {

            // 기간별 날짜 범위 설정
            switch ($period) {
                case 'last_month':
                    $startOfMonth = now()->subMonth()->startOfMonth();
                    $endOfMonth = now()->subMonth()->endOfMonth();
                    break;
                case 'last_3_months':
                    $startOfMonth = now()->subMonths(2)->startOfMonth();
                    $endOfMonth = now()->endOfMonth();
                    break;
                case 'this_month':
                default:
                    $startOfMonth = now()->startOfMonth();
                    $endOfMonth = now()->endOfMonth();
                    break;
            }

            $salesQuery = Sale::whereBetween('sale_date', [$startOfMonth, $endOfMonth]);

            if ($type === 'branch') {
                // TOP N 지사 리스트
                $rankings = $salesQuery->select('branch_id', DB::raw('SUM(settlement_amount) as total'))
                    ->groupBy('branch_id')
                    ->orderByDesc('total')
                    ->limit($limit)
                    ->get();

                // 지사 정보를 한 번에 로드 (N+1 방지)
                $branchIds = $rankings->pluck('branch_id')->toArray();
                $branches = Branch::whereIn('id', $branchIds)->get()->keyBy('id');

                $topList = [];
                foreach ($rankings as $index => $ranking) {
                    $branch = $branches->get($ranking->branch_id);
                    if ($branch) {
                        $topList[] = [
                            'rank' => $index + 1,
                            'id' => $branch->id,
                            'name' => $branch->name,
                            'code' => $branch->code,
                            'total_sales' => floatval($ranking->total),
                            'is_current_user' => $user->branch_id == $branch->id,
                        ];
                    }
                }

            } else { // store
                // TOP N 매장 리스트
                $storeQuery = $salesQuery->select('store_id', DB::raw('SUM(settlement_amount) as total'));

                // 지사/매장 계정은 자기 지사 내부만
                if ($user->isBranch() || $user->isStore()) {
                    $storeQuery->where('branch_id', $user->branch_id);
                }

                $rankings = $storeQuery->groupBy('store_id')
                    ->orderByDesc('total')
                    ->limit($limit)
                    ->get();

                // 매장 정보를 한 번에 로드 (N+1 방지)
                $storeIds = $rankings->pluck('store_id')->toArray();
                $stores = Store::with('branch')->whereIn('id', $storeIds)->get()->keyBy('id');

                $topList = [];
                foreach ($rankings as $index => $ranking) {
                    $store = $stores->get($ranking->store_id);
                    if ($store) {
                        $topList[] = [
                            'rank' => $index + 1,
                            'id' => $store->id,
                            'name' => $store->name,
                            'code' => $store->code,
                            'branch_name' => $store->branch->name ?? '미지정',
                            'total_sales' => floatval($ranking->total),
                            'is_current_user' => $user->store_id == $store->id,
                        ];
                    }
                }
            }

                return response()->json([
                    'success' => true,
                    'data' => $topList,
                    'meta' => [
                        'type' => $type,
                        'limit' => $limit,
                        'scope' => $user->isHeadquarters() ? 'nationwide' : 'branch_only',
                        'user_role' => $user->role,
                        'period' => $period,
                        'period_label' => $this->getPeriodLabel($period),
                        'date_range' => [
                            'start' => $startOfMonth->format('Y-m-d'),
                            'end' => $endOfMonth->format('Y-m-d'),
                        ],
                        'cached_at' => now()->toISOString(),
                        'generated_at' => now()->toISOString(),
                    ],
                ]);
            }); // Cache::remember 종료

        } catch (\Exception $e) {
            Log::error('Top list API error', ['error' => $e->getMessage(), 'type' => $request->query('type')]);

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 판매 추세 데이터 (권한별 필터링)
     */
    public function salesTrend(Request $request)
    {
        try {
            $days = min($request->get('days', 30), 90); // 최대 90일
            $user = auth()->user();

            // 캐시 키 생성 (기간별로 다른 캐시)
            $cacheKey = sprintf(
                'sales_trend_%s_%s_%s_%s',
                $user->role,
                $user->id,
                $days,
                now()->format('Y-m-d-H:i')
            );
            // 5분 단위로 반올림
            $cacheKey = substr($cacheKey, 0, -1) . floor(now()->minute / 5) * 5;

            return Cache::remember($cacheKey, 300, function () use ($user, $days, $request) {

            // 날짜 범위 계산
            $endDate = now()->endOfDay()->format('Y-m-d H:i:s');
            $startDate = now()->subDays($days - 1)->startOfDay()->format('Y-m-d H:i:s');

            // 권한별 필터링
            $query = Sale::query();

            if ($user->isBranch()) {
                $query->where('branch_id', $user->branch_id);
            } elseif ($user->isStore()) {
                $query->where('store_id', $user->store_id);
            }

            // 일별 집계 - DB 독립적
            $dailyData = $query->whereBetween('sale_date', [$startDate, $endDate])
                ->selectRaw('sale_date as date')
                ->selectRaw('COUNT(*) as activations')
                ->selectRaw('SUM(settlement_amount) as sales')
                ->groupBy('sale_date')
                ->orderBy('sale_date')
                ->get();

            // 날짜별 데이터 맵 생성
            $dataMap = [];
            foreach ($dailyData as $data) {
                // date를 Y-m-d 형식으로 변환
                $dateKey = date('Y-m-d', strtotime($data->date));
                $dataMap[$dateKey] = [
                    'activations' => $data->activations,
                    'sales' => floatval($data->sales),
                ];
            }

            // 모든 날짜에 대한 데이터 생성 (빈 날짜 포함)
            $trendData = [];
            for ($i = 0; $i < $days; $i++) {
                $date = now()->subDays($days - 1 - $i)->format('Y-m-d');
                $dayLabel = now()->subDays($days - 1 - $i)->format('m/d');

                $trendData[] = [
                    'date' => $date,
                    'day_label' => $dayLabel,
                    'activations' => $dataMap[$date]['activations'] ?? 0,
                    'sales' => $dataMap[$date]['sales'] ?? 0,
                ];
            }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'trend_data' => $trendData,
                        'summary' => [
                            'total_activations' => array_sum(array_column($trendData, 'activations')),
                            'total_sales' => array_sum(array_column($trendData, 'sales')),
                            'average_daily_activations' => round(array_sum(array_column($trendData, 'activations')) / $days, 1),
                            'average_daily_sales' => round(array_sum(array_column($trendData, 'sales')) / $days, 0),
                        ],
                    ],
                    'meta' => [
                        'days' => $days,
                        'start_date' => date('Y-m-d', strtotime($startDate)),
                        'end_date' => date('Y-m-d', strtotime($endDate)),
                        'user_role' => $user->role,
                        'cached_at' => now()->toISOString(),
                        'generated_at' => now()->toISOString(),
                    ],
                ]);
            }); // Cache::remember 종료

        } catch (\Exception $e) {
            Log::error('Sales trend API error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 매장별 목표 달성률 계산 (하드코딩 제거)
     */
    private function calculateStoreTargetAchievement($storeId, $actualSales)
    {
        try {
            // 날짜 범위 계산
            $startOfMonth = now()->startOfMonth()->format('Y-m-d');
            $endOfMonth = now()->endOfMonth()->format('Y-m-d');

            // 매장별 목표 조회 - DB 독립적
            $goal = \App\Models\Goal::where('target_type', 'store')
                ->where('target_id', $storeId)
                ->where('period_type', 'monthly')
                ->where('is_active', '=', config('database.default') === 'pgsql' ? \DB::raw('true') : true)
                ->whereBetween('period_start', [$startOfMonth, $endOfMonth])
                ->first();

            $storeTarget = $goal ? $goal->sales_target : config('sales.default_targets.store.monthly_sales');

            return $actualSales > 0 ? round(($actualSales / $storeTarget) * 100, 1) : 0;
        } catch (\Exception $e) {
            Log::warning('Store target calculation failed', [
                'store_id' => $storeId,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * KPI 통계 데이터 (권한별 필터링)
     */
    public function kpi(Request $request)
    {
        try {
            Log::info('KPI API 시작', ['request' => $request->all()]);

            $days = min($request->get('days', 30), 90); // 최대 90일
            $storeId = $request->get('store'); // 특정 매장 필터링
            $user = auth()->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'error' => '인증이 필요합니다',
                ], 401);
            }

            // 캐시 키 생성
            $cacheKey = sprintf(
                'kpi_%s_%s_%s_%s_%s',
                $user->role,
                $user->id,
                $days,
                $storeId ?? 'all',
                now()->format('Y-m-d-H:i')
            );
            // 5분 단위로 반올림
            $cacheKey = substr($cacheKey, 0, -1) . floor(now()->minute / 5) * 5;

            return Cache::remember($cacheKey, 300, function () use ($user, $days, $storeId, $request) {

            Log::info('KPI API 사용자 정보', [
                'user_id' => $user->id,
                'role' => $user->role,
                'store_id' => $user->store_id,
                'branch_id' => $user->branch_id,
            ]);

            // 날짜 범위 계산
            $endDate = now()->endOfDay()->format('Y-m-d H:i:s');
            $startDate = now()->subDays($days - 1)->startOfDay()->format('Y-m-d H:i:s');

            // 권한별 필터링
            $query = Sale::query();

            if ($user->isBranch()) {
                $query->where('branch_id', $user->branch_id);
            } elseif ($user->isStore()) {
                $query->where('store_id', $user->store_id);
            }

            // 특정 매장 필터링 (지사 계정의 경우)
            if ($storeId && $user->isBranch()) {
                $query->where('store_id', $storeId);
            }

            // 기간별 데이터 조회
            Log::info('KPI API 메인 쿼리 실행', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'store_id' => $storeId,
            ]);

            // 세금 제거 (커밋 427845b6): 마진 = 정산금
            $salesData = $query->whereBetween('sale_date', [$startDate, $endDate])
                ->selectRaw('COUNT(*) as total_activations')
                ->selectRaw('SUM(settlement_amount) as total_sales')
                ->selectRaw('SUM(settlement_amount) as total_margin')
                ->selectRaw('AVG(settlement_amount) as avg_sale_amount')
                ->first();

            Log::info('KPI API 메인 쿼리 결과', [
                'total_activations' => $salesData->total_activations ?? 0,
                'total_sales' => $salesData->total_sales ?? 0,
            ]);

            // 오늘 데이터
            $todayData = Sale::query();
            if ($user->isBranch()) {
                $todayData->where('branch_id', $user->branch_id);
            } elseif ($user->isStore()) {
                $todayData->where('store_id', $user->store_id);
            }

            if ($storeId && $user->isBranch()) {
                $todayData->where('store_id', $storeId);
            }

            $todayStats = $todayData->whereDate('sale_date', now()->format('Y-m-d'))
                ->selectRaw('COUNT(*) as today_activations')
                ->selectRaw('SUM(settlement_amount) as today_sales')
                ->first();

            // 이번달 데이터
            $monthlyData = Sale::query();
            if ($user->isBranch()) {
                $monthlyData->where('branch_id', $user->branch_id);
            } elseif ($user->isStore()) {
                $monthlyData->where('store_id', $user->store_id);
            }

            if ($storeId && $user->isBranch()) {
                $monthlyData->where('store_id', $storeId);
            }

            $monthlyStats = $monthlyData->whereBetween('sale_date', [
                now()->startOfMonth()->format('Y-m-d'),
                now()->endOfMonth()->format('Y-m-d'),
            ])
                ->selectRaw('COUNT(*) as month_activations')
                ->selectRaw('SUM(settlement_amount) as month_sales')
                ->first();

            // 목표 달성률 계산
            $targetSales = 50000000; // 기본 목표 5천만원
            if ($user->isStore() && $user->store_id) {
                $targetSales = 500000; // 매장 기본 목표 50만원
            }

            $achievementRate = $monthlyStats->month_sales > 0
                ? round(($monthlyStats->month_sales / $targetSales) * 100, 1)
                : 0;

            // 성장률 계산 (전월 대비)
            $lastMonthData = Sale::query();
            if ($user->isBranch()) {
                $lastMonthData->where('branch_id', $user->branch_id);
            } elseif ($user->isStore()) {
                $lastMonthData->where('store_id', $user->store_id);
            }

            if ($storeId && $user->isBranch()) {
                $lastMonthData->where('store_id', $storeId);
            }

            $lastMonthSales = $lastMonthData->whereBetween('sale_date', [
                now()->subMonth()->startOfMonth()->format('Y-m-d'),
                now()->subMonth()->endOfMonth()->format('Y-m-d'),
            ])->sum('settlement_amount');

            $growthRate = $lastMonthSales > 0
                ? round((($monthlyStats->month_sales - $lastMonthSales) / $lastMonthSales) * 100, 1)
                : 0;

                return response()->json([
                    'success' => true,
                    'data' => [
                        'overview' => [
                            'total_activations' => (int) $salesData->total_activations,
                            'total_sales' => (float) $salesData->total_sales,
                            'total_margin' => (float) $salesData->total_margin,
                            'avg_sale_amount' => (float) $salesData->avg_sale_amount,
                        ],
                        'today' => [
                            'activations' => (int) $todayStats->today_activations,
                            'sales' => (float) $todayStats->today_sales,
                        ],
                        'monthly' => [
                            'activations' => (int) $monthlyStats->month_activations,
                            'sales' => (float) $monthlyStats->month_sales,
                            'target_sales' => $targetSales,
                            'achievement_rate' => $achievementRate,
                            'growth_rate' => $growthRate,
                        ],
                    ],
                    'meta' => [
                        'days' => $days,
                        'store_filter' => $storeId,
                        'user_role' => $user->role,
                        'period' => [
                            'start' => now()->subDays($days - 1)->format('Y-m-d'),
                            'end' => now()->format('Y-m-d'),
                        ],
                        'cached_at' => now()->toISOString(),
                        'generated_at' => now()->toISOString(),
                    ],
                ]);
            }); // Cache::remember 종료

        } catch (\Exception $e) {
            Log::error('KPI API error', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'user_id' => auth()->id(),
                'request_params' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get period label in Korean
     */
    private function getPeriodLabel($period)
    {
        switch ($period) {
            case 'last_month':
                return '지난 달';
            case 'last_3_months':
                return '최근 3개월';
            case 'this_month':
            default:
                return '이번 달';
        }
    }
}
