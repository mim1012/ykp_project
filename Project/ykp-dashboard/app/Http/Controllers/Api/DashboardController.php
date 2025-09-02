<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\DailyExpense;
use App\Models\FixedExpense;
use App\Models\DealerProfile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    // 클린코드: 상수 정의 (매직 넘버 제거)
    private const MONTHLY_TARGET = 50000000; // 5천만원 목표
    private const DEFAULT_RANKING_LIMIT = 10;
    private const DEFAULT_TREND_DAYS = 30;
    /**
     * 전체 현황 요약 - 메인 대시보드용
     */
    public function overview(Request $request): JsonResponse
    {
        try {
            Log::info('Dashboard overview API called', ['user_id' => auth()->id()]);
            
            $user = auth()->user();
            $baseQuery = $this->getAuthorizedSalesQuery($user);
            
            // 클린코드: 단일 책임 원칙 적용
            $todayStats = $this->getTodayStatistics($baseQuery);
            $monthStats = $this->getMonthStatistics($baseQuery);
            $goals = $this->calculateGoals($monthStats['sales']);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'today' => $todayStats,
                    'month' => $monthStats,
                    'goals' => $goals
                ],
                'timestamp' => now()->toISOString(),
                'user_role' => $user?->role ?? 'guest'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Dashboard overview API error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => config('app.debug') ? $e->getMessage() : 'Data loading failed'
            ], 500);
        }
    }
    
    // 클린코드: 권한별 쿼리 생성 (DRY 원칙)
    private function getAuthorizedSalesQuery($user = null)
    {
        $query = Sale::query();
        
        if ($user) {
            try {
                $accessibleStoreIds = $user->getAccessibleStoreIds();
                if (!empty($accessibleStoreIds)) {
                    $query->whereIn('store_id', $accessibleStoreIds);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get accessible store IDs', ['user_id' => $user->id]);
                // 권한 확인 실패 시 빈 결과 반환
                $query->whereRaw('1 = 0'); 
            }
        }
        
        return $query;
    }
    
    // 클린코드: 오늘 통계 계산 (SRP)
    private function getTodayStatistics($baseQuery): array
    {
        $today = now()->toDateString();
        $todayQuery = (clone $baseQuery)->whereDate('sale_date', $today);
        
        return [
            'sales' => $todayQuery->sum('settlement_amount') ?? 0,
            'activations' => $todayQuery->count(),
            'date' => $today
        ];
    }
    
    // 클린코드: 월간 통계 계산 (SRP)
    private function getMonthStatistics($baseQuery): array
    {
        $currentMonth = now()->format('Y-m');
        $monthQuery = (clone $baseQuery)->whereYear('sale_date', now()->year)
                                        ->whereMonth('sale_date', now()->month);
        
        $monthSales = $monthQuery->sum('settlement_amount') ?? 0;
        $avgMargin = $monthQuery->where('settlement_amount', '>', 0)
                               ->avg(DB::raw('COALESCE(margin_after_tax / NULLIF(settlement_amount, 0) * 100, 0)')) ?? 0;
        
        // VAT 계산 (안전한 계산)
        $vatIncludedSales = $monthQuery->sum(DB::raw('settlement_amount + COALESCE(tax, 0)')) ?? 0;
        
        // 전월 대비 증감률
        $lastMonthSales = (clone $baseQuery)->whereYear('sale_date', now()->subMonth()->year)
                                           ->whereMonth('sale_date', now()->subMonth()->month)
                                           ->sum('settlement_amount') ?? 0;
        
        $growthRate = $lastMonthSales > 0 ? 
            round((($monthSales - $lastMonthSales) / $lastMonthSales) * 100, 1) : 0;
        
        return [
            'sales' => $monthSales,
            'activations' => $monthQuery->count(),
            'vat_included_sales' => $vatIncludedSales,
            'year_month' => $currentMonth,
            'growth_rate' => $growthRate,
            'avg_margin' => round($avgMargin, 1)
        ];
    }
    
    // 클린코드: 목표 달성률 계산 (SRP)
    private function calculateGoals(float $monthSales): array
    {
        return [
            'monthly_target' => self::MONTHLY_TARGET,
            'achievement_rate' => round(($monthSales / self::MONTHLY_TARGET) * 100, 1)
        ];
    }

    /**
     * 30일 매출 추이 데이터
     */
    public function salesTrend(Request $request): JsonResponse
    {
        try {
            $days = min($request->get('days', self::DEFAULT_TREND_DAYS), 90); // 최대 90일 제한
            $endDate = now();
            $startDate = $endDate->copy()->subDays($days - 1);
            $user = auth()->user();
            
            $baseQuery = $this->getAuthorizedSalesQuery($user);
        
        $trendData = [];
        
        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dailyQuery = (clone $baseQuery)->whereDate('sale_date', $date->toDateString());
            $dailySales = $dailyQuery->sum('settlement_amount') ?? 0;
            
            $trendData[] = [
                'date' => $date->toDateString(),
                'day_label' => $date->format('j일'),
                'sales' => $dailySales,
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
            ],
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * 대리점별 성과 분석
     */
    public function dealerPerformance(Request $request): JsonResponse
    {
        $yearMonth = $request->get('year_month', now()->format('Y-m'));
        
        $dealerStats = Sale::with('dealerProfile')
            ->whereYear('sale_date', substr($yearMonth, 0, 4))
            ->whereMonth('sale_date', substr($yearMonth, 5, 2))
            ->select([
                'dealer_code',
                DB::raw('COUNT(*) as activations'),
                DB::raw('SUM(settlement_amount) as total_sales'),
                DB::raw('SUM(margin_after_tax) as total_margin'),
                DB::raw('AVG(margin_after_tax) as avg_margin')
            ])
            ->groupBy('dealer_code')
            ->orderBy('total_sales', 'desc')
            ->get();

        // 통신사별 분석
        $carrierStats = Sale::whereYear('sale_date', substr($yearMonth, 0, 4))
            ->whereMonth('sale_date', substr($yearMonth, 5, 2))
            ->select([
                'carrier',
                DB::raw('COUNT(*) as count'),
                DB::raw('(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM sales WHERE YEAR(sale_date) = ? AND MONTH(sale_date) = ?)) as percentage')
            ])
            ->addBinding(substr($yearMonth, 0, 4))
            ->addBinding(substr($yearMonth, 5, 2))
            ->groupBy('carrier')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'year_month' => $yearMonth,
                'dealer_performance' => $dealerStats,
                'carrier_breakdown' => $carrierStats,
                'total_dealers' => $dealerStats->count(),
                'best_performer' => $dealerStats->first()
            ],
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * 재무 현황 요약
     */
    public function financialSummary(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());
        
        // 수익 계산
        $totalRevenue = Sale::whereBetween('sale_date', [$startDate, $endDate])
            ->sum('settlement_amount');
        
        $totalMargin = Sale::whereBetween('sale_date', [$startDate, $endDate])
            ->sum('margin_after_tax');
        
        // 지출 계산
        $dailyExpenses = DailyExpense::whereBetween('expense_date', [$startDate, $endDate])
            ->sum('amount');
        
        $fixedExpenses = FixedExpense::whereYear('year_month', now()->year)
            ->whereMonth('year_month', now()->month)
            ->sum('amount');
        
        $totalExpenses = $dailyExpenses + $fixedExpenses;
        $netProfit = $totalMargin - $totalExpenses;
        
        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'revenue' => [
                    'total_revenue' => $totalRevenue,
                    'total_margin' => $totalMargin,
                    'margin_rate' => $totalRevenue > 0 ? round(($totalMargin / $totalRevenue) * 100, 2) : 0
                ],
                'expenses' => [
                    'daily_expenses' => $dailyExpenses,
                    'fixed_expenses' => $fixedExpenses,
                    'total_expenses' => $totalExpenses
                ],
                'profit' => [
                    'net_profit' => $netProfit,
                    'profit_rate' => $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 2) : 0
                ]
            ],
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * 매장별 랭킹 (일별/월별)
     */
    public function storeRanking(Request $request): JsonResponse
    {
        try {
            $period = $request->get('period', 'daily');
            $limit = min($request->get('limit', self::DEFAULT_RANKING_LIMIT), 50); // 최대 50개 제한
            $user = auth()->user();
            
            $baseQuery = $this->getAuthorizedSalesQuery($user)
                             ->with(['store:id,name,code', 'store.branch:id,name']);
            
            $query = $this->applyPeriodFilter($baseQuery, $period)
                         ->select([
                'store_id',
                DB::raw('COUNT(*) as activation_count'),
                DB::raw('SUM(settlement_amount) as total_sales'),
                DB::raw('SUM(margin_after_tax) as total_margin'),
                DB::raw('AVG(margin_after_tax) as avg_margin'),
                DB::raw('MAX(sale_date) as last_sale_date')
            ]);
            
        switch ($period) {
            case 'daily':
                $query->whereDate('sale_date', now()->toDateString());
                break;
            case 'weekly':
                $query->whereBetween('sale_date', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'monthly':
                $query->whereYear('sale_date', now()->year)
                      ->whereMonth('sale_date', now()->month);
                break;
        }
        
        $rankings = $query->groupBy('store_id')
            ->orderBy('total_sales', 'desc')
            ->take($limit)
            ->get()
            ->map(function ($item, $index) {
                return [
                    'rank' => $index + 1,
                    'store_id' => $item->store_id,
                    'store_name' => $item->store->name ?? '알 수 없음',
                    'store_code' => $item->store->code ?? '',
                    'branch_name' => $item->store->branch->name ?? '본사',
                    'activation_count' => $item->activation_count,
                    'total_sales' => round($item->total_sales, 0),
                    'total_margin' => round($item->total_margin, 0),
                    'avg_margin' => round($item->avg_margin, 0),
                    'last_sale_date' => $item->last_sale_date,
                ];
            });
            
        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'rankings' => $rankings,
                'generated_at' => now()->toISOString()
            ]
        ]);
    }

    /**
     * 일별 매출 통계 리포트
     */
    public function dailySalesReport(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());
        
        $dailyStats = Sale::with(['store:id,name,code'])
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->select([
                'sale_date',
                'store_id',
                DB::raw('COUNT(*) as activation_count'),
                DB::raw('SUM(settlement_amount) as daily_sales'),
                DB::raw('SUM(margin_after_tax) as daily_margin'),
                DB::raw('AVG(settlement_amount) as avg_sale_amount')
            ])
            ->groupBy('sale_date', 'store_id')
            ->orderBy('sale_date', 'desc')
            ->orderBy('daily_sales', 'desc')
            ->get()
            ->groupBy('sale_date')
            ->map(function ($dayData, $date) {
                $dayTotal = $dayData->sum('daily_sales');
                $dayActivations = $dayData->sum('activation_count');
                
                return [
                    'date' => $date,
                    'total_sales' => round($dayTotal, 0),
                    'total_activations' => $dayActivations,
                    'avg_sale_per_activation' => $dayActivations > 0 ? round($dayTotal / $dayActivations, 0) : 0,
                    'stores' => $dayData->map(function ($store) {
                        return [
                            'store_id' => $store->store_id,
                            'store_name' => $store->store->name ?? '알 수 없음',
                            'store_code' => $store->store->code ?? '',
                            'activation_count' => $store->activation_count,
                            'daily_sales' => round($store->daily_sales, 0),
                            'daily_margin' => round($store->daily_margin, 0),
                            'avg_sale_amount' => round($store->avg_sale_amount, 0),
                        ];
                    })->sortByDesc('daily_sales')->values()
                ];
            });
            
        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'daily_stats' => $dailyStats->values(),
                'summary' => [
                    'total_days' => $dailyStats->count(),
                    'total_sales' => $dailyStats->sum(fn($day) => $day['total_sales']),
                    'total_activations' => $dailyStats->sum(fn($day) => $day['total_activations'])
                ]
            ]
        ]);
    }
    
    // 클린코드: 기간별 필터 적용 (DRY 원칙)
    private function applyPeriodFilter($query, string $period)
    {
        switch ($period) {
            case 'daily':
                return $query->whereDate('sale_date', now()->toDateString());
            case 'weekly':
                return $query->whereBetween('sale_date', [now()->startOfWeek(), now()->endOfWeek()]);
            case 'monthly':
                return $query->whereYear('sale_date', now()->year)
                            ->whereMonth('sale_date', now()->month);
            default:
                return $query->whereDate('sale_date', now()->toDateString());
        }
    }
}
