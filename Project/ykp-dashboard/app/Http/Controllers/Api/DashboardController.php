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

class DashboardController extends Controller
{
    /**
     * 대시보드 개요 데이터 (통계 페이지 메인)
     */
    public function overview(Request $request)
    {
        try {
            Log::info('Dashboard overview API called', ['user_id' => auth()->id()]);
            
            // 기본 통계
            $totalStores = Store::count();
            $totalBranches = Branch::count();
            $totalUsers = User::count();
            
            // 이번달 매출 (실제 데이터)
            $thisMonth = now()->format('Y-m');
            $thisMonthSales = Sale::whereRaw("DATE_FORMAT(sale_date, '%Y-%m') = ?", [$thisMonth])
                                ->sum('settlement_amount');
            
            // 오늘 개통 건수
            $todaySales = Sale::whereDate('sale_date', today())->count();
            
            // 목표 달성률 계산 (월 5천만원 기준)
            $monthlyTarget = 50000000;
            $achievementRate = $thisMonthSales > 0 ? round(($thisMonthSales / $monthlyTarget) * 100, 1) : 0;
            
            Log::info('Dashboard overview calculated', [
                'stores' => $totalStores,
                'branches' => $totalBranches,
                'this_month_sales' => $thisMonthSales,
                'achievement_rate' => $achievementRate
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total_stores' => $totalStores,
                    'total_branches' => $totalBranches,
                    'total_users' => $totalUsers,
                    'this_month_sales' => floatval($thisMonthSales),
                    'today_activations' => $todaySales,
                    'monthly_target' => $monthlyTarget,
                    'achievement_rate' => $achievementRate,
                    'currency' => 'KRW'
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
                    $query->whereMonth('sale_date', now()->month)
                          ->whereYear('sale_date', now()->year);
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
                        'target_achievement' => round((floatval($ranking->total_sales) / 500000) * 100, 1) // 매장별 월 50만원 목표
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
}