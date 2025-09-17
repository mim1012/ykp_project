<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Helpers\DatabaseHelper;

class DashboardController extends Controller
{
    /**
     * 대시보드 개요 데이터 (통계 페이지 메인)
     */
    public function overview(Request $request)
    {
        try {
            Log::info('Dashboard overview API called', ['user_id' => auth()->id()]);

            $user = auth()->user();

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

            // 전체/활성 구분된 통계
            $totalStores = $storeQuery->count();
            $activeStores = $storeQuery->where('status', 'active')->count();
            $totalBranches = $branchQuery->count();
            $activeBranches = $branchQuery->where('status', 'active')->count();
            $totalUsers = $userQuery->count();
            $activeUsers = $userQuery->where('status', 'active')->count();

            // 매출 데이터가 있는 매장 수 (실제 활동 매장) - PostgreSQL/SQLite 호환
            $thisMonth = now()->format('Y-m');
            $dateFunction = config('database.default') === 'pgsql'
                ? "TO_CHAR(sale_date, 'YYYY-MM')"
                : "strftime('%Y-%m', sale_date)";

            $salesActiveStores = $saleQuery->clone()
                                   ->whereRaw("{$dateFunction} = ?", [$thisMonth])
                                   ->distinct('store_id')
                                   ->count();

            // 이번달 매출 (실제 데이터) - PostgreSQL/SQLite 호환
            $thisMonthSales = $saleQuery->clone()
                                ->whereRaw("{$dateFunction} = ?", [$thisMonth])
                                ->sum('settlement_amount');

            // 오늘 개통 건수
            $todaySales = $saleQuery->clone()->whereDate('sale_date', today())->count();
            
            // 🔄 목표 달성률 계산 (실제 목표 API 기반, 하드코딩 제거)
            $goal = \App\Models\Goal::where('target_type', 'system')
                ->where('period_type', 'monthly')
                ->where('is_active', true)
                ->whereRaw("DATE_FORMAT(period_start, '%Y-%m') = ?", [now()->format('Y-m')])
                ->first();

            $monthlyTarget = $goal ? $goal->sales_target : config('sales.default_targets.system.monthly_sales');
            $achievementRate = $thisMonthSales > 0 ? round(($thisMonthSales / $monthlyTarget) * 100, 1) : 0;
            
            Log::info('Dashboard overview calculated', [
                'total_stores' => $totalStores,
                'active_stores' => $activeStores,
                'sales_active_stores' => $salesActiveStores,
                'total_branches' => $totalBranches,
                'active_branches' => $activeBranches,
                'this_month_sales' => $thisMonthSales,
                'achievement_rate' => $achievementRate
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'stores' => [
                        'total' => $totalStores,
                        'active' => $activeStores,
                        'with_sales' => $salesActiveStores
                    ],
                    'branches' => [
                        'total' => $totalBranches,
                        'active' => $activeBranches
                    ],
                    'users' => [
                        'total' => $totalUsers,
                        'active' => $activeUsers,
                        'headquarters' => User::where('role', 'headquarters')->count(),
                        'branch_managers' => User::where('role', 'branch')->count(),
                        'store_staff' => User::where('role', 'store')->count()
                    ],
                    'this_month_sales' => floatval($thisMonthSales),
                    'today_activations' => $todaySales,
                    'monthly_target' => $monthlyTarget,
                    'achievement_rate' => $achievementRate,
                    'currency' => 'KRW',
                    'meta' => [
                        'generated_at' => now()->toISOString(),
                        'period' => $thisMonth
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Dashboard overview API error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => '대시보드 데이터 로딩 중 오류가 발생했습니다.',
                'debug' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 매장별 랭킹 데이터
     */
    public function storeRanking(Request $request)
    {
        try {
            $period = $request->get('period', 'monthly'); 
            $limit = min($request->get('limit', 10), 50);
            
            // 기간별 필터링
            $query = Sale::with(['store', 'store.branch']);
            
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
            
            // 매장 정보 로드
            $rankedStores = [];
            foreach ($rankings as $index => $ranking) {
                $store = Store::with('branch')->find($ranking->store_id);
                if ($store) {
                    $rankedStores[] = [
                        'rank' => $index + 1,
                        'store_id' => $store->id,
                        'store_name' => $store->name,
                        'branch_name' => $store->branch->name ?? '미지정',
                        'total_sales' => floatval($ranking->total_sales),
                        'activation_count' => $ranking->activation_count,
                        'target_achievement' => $this->calculateStoreTargetAchievement($store->id, floatval($ranking->total_sales))
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => $rankedStores,
                'meta' => [
                    'period' => $period,
                    'total_stores_with_sales' => count($rankedStores),
                    'generated_at' => now()->toISOString()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Store ranking API error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'error' => '매장 랭킹 데이터 로딩 오류',
                'debug' => $e->getMessage()
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
            
            $sales = Sale::whereBetween('sale_date', [$startDate, $endDate]);
            
            $totalSales = $sales->sum('settlement_amount');
            $totalMargin = $sales->sum('pre_tax_margin');
            $totalExpenses = $sales->sum('tax_amount'); // 세금을 지출로 근사
            $netProfit = $totalMargin - $totalExpenses;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total_sales' => floatval($totalSales),
                    'total_margin' => floatval($totalMargin), 
                    'total_expenses' => floatval($totalExpenses),
                    'net_profit' => floatval($netProfit),
                    'period' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Financial summary API error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 대리점별 성과 데이터
     */
    public function dealerPerformance(Request $request)
    {
        try {
            $yearMonth = $request->get('year_month', now()->format('Y-m'));
            
            $performances = Sale::with(['store', 'store.branch'])
                              ->whereRaw("DATE_FORMAT(sale_date, '%Y-%m') = ?", [$yearMonth])
                              ->select('agency')
                              ->selectRaw('COUNT(*) as count')
                              ->selectRaw('SUM(settlement_amount) as total_amount')
                              ->groupBy('agency')
                              ->orderBy('total_amount', 'desc')
                              ->get();
            
            $data = $performances->map(function($perf) {
                return [
                    'agency_name' => $perf->agency,
                    'activation_count' => $perf->count,
                    'total_sales' => floatval($perf->total_amount),
                    'market_share' => 0 // 계산 로직 추가 필요
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => ['year_month' => $yearMonth]
            ]);
            
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
                $branchRank = $branchRankings->search(function($ranking) use ($user) {
                    return $ranking->branch_id == $user->branch_id;
                }) + 1;
                
                if ($branchRank === 0) $branchRank = null; // 데이터 없으면 null
            }
            
            // 2. 매장 순위 계산 (권한별 필터링)
            $storeQuery = $salesQuery->clone()
                ->select('store_id', DB::raw('SUM(settlement_amount) as total'));
            
            // 지사/매장 계정은 자기 지사 내부 순위만
            if ($user->isBranch() || $user->isStore()) {
                $storeQuery->where('branch_id', $user->branch_id);
            }
            
            $storeRankings = $storeQuery->groupBy('store_id')
                ->orderByDesc('total')
                ->get();
            
            $storeRank = null;
            $storeTotal = $storeRankings->count();
            
            if ($user->store_id) {
                $storeRank = $storeRankings->search(function($ranking) use ($user) {
                    return $ranking->store_id == $user->store_id;
                }) + 1;
                
                if ($storeRank === 0) $storeRank = null; // 데이터 없으면 null
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'branch' => [
                        'rank' => $branchRank,
                        'total' => $branchTotal,
                        'user_branch_id' => $user->branch_id
                    ],
                    'store' => [
                        'rank' => $storeRank,
                        'total' => $storeTotal,
                        'user_store_id' => $user->store_id,
                        'scope' => $user->isHeadquarters() ? 'nationwide' : 'branch_only'
                    ]
                ],
                'meta' => [
                    'user_role' => $user->role,
                    'period' => now()->format('Y-m'),
                    'generated_at' => now()->toISOString()
                ]
            ]);
            
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
            $user = auth()->user();
            
            $startOfMonth = now()->startOfMonth();
            $endOfMonth = now()->endOfMonth();
            $salesQuery = Sale::whereBetween('sale_date', [$startOfMonth, $endOfMonth]);
            
            if ($type === 'branch') {
                // TOP N 지사 리스트
                $rankings = $salesQuery->select('branch_id', DB::raw('SUM(settlement_amount) as total'))
                    ->groupBy('branch_id')
                    ->orderByDesc('total')
                    ->limit($limit)
                    ->get();
                
                $topList = [];
                foreach ($rankings as $index => $ranking) {
                    $branch = Branch::find($ranking->branch_id);
                    if ($branch) {
                        $topList[] = [
                            'rank' => $index + 1,
                            'id' => $branch->id,
                            'name' => $branch->name,
                            'code' => $branch->code,
                            'total_sales' => floatval($ranking->total),
                            'is_current_user' => $user->branch_id == $branch->id
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
                
                $topList = [];
                foreach ($rankings as $index => $ranking) {
                    $store = Store::with('branch')->find($ranking->store_id);
                    if ($store) {
                        $topList[] = [
                            'rank' => $index + 1,
                            'id' => $store->id,
                            'name' => $store->name,
                            'code' => $store->code,
                            'branch_name' => $store->branch->name ?? '미지정',
                            'total_sales' => floatval($ranking->total),
                            'is_current_user' => $user->store_id == $store->id
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
                    'period' => now()->format('Y-m')
                ]
            ]);
            
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

            // 날짜 범위 계산
            $endDate = now()->endOfDay();
            $startDate = now()->subDays($days - 1)->startOfDay();

            // 권한별 필터링
            $query = Sale::query();

            if ($user->isBranch()) {
                $query->where('branch_id', $user->branch_id);
            } elseif ($user->isStore()) {
                $query->where('store_id', $user->store_id);
            }

            // 일별 집계
            $dailyData = $query->whereBetween('sale_date', [$startDate, $endDate])
                ->selectRaw('DATE(sale_date) as date')
                ->selectRaw('COUNT(*) as activations')
                ->selectRaw('SUM(settlement_amount) as sales')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // 날짜별 데이터 맵 생성
            $dataMap = [];
            foreach ($dailyData as $data) {
                $dataMap[$data->date] = [
                    'activations' => $data->activations,
                    'sales' => floatval($data->sales)
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
                    'sales' => $dataMap[$date]['sales'] ?? 0
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
                        'average_daily_sales' => round(array_sum(array_column($trendData, 'sales')) / $days, 0)
                    ]
                ],
                'meta' => [
                    'days' => $days,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'user_role' => $user->role,
                    'generated_at' => now()->toISOString()
                ]
            ]);

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
            // 매장별 목표 조회
            $goal = \App\Models\Goal::where('target_type', 'store')
                ->where('target_id', $storeId)
                ->where('period_type', 'monthly')
                ->where('is_active', true)
                ->whereRaw("DATE_FORMAT(period_start, '%Y-%m') = ?", [now()->format('Y-m')])
                ->first();

            $storeTarget = $goal ? $goal->sales_target : config('sales.default_targets.store.monthly_sales');

            return $actualSales > 0 ? round(($actualSales / $storeTarget) * 100, 1) : 0;
        } catch (\Exception $e) {
            Log::warning('Store target calculation failed', [
                'store_id' => $storeId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
}