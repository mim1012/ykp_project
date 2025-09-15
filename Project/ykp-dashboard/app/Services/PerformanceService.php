<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PerformanceService
{
    /**
     * 매장 순위 변화 계산 (전월 대비)
     */
    public function calculateRankChange(int $storeId, ?int $currentRank): int
    {
        try {
            $cacheKey = "rank_change_{$storeId}_" . now()->format('Y-m');

            return Cache::remember($cacheKey, 3600, function () use ($storeId, $currentRank) {
                // 전월 순위 계산
                $lastMonth = now()->subMonth();
                $lastMonthStats = Sale::join('stores', 'sales.store_id', '=', 'stores.id')
                    ->whereBetween('sales.sale_date', [$lastMonth->startOfMonth(), $lastMonth->endOfMonth()])
                    ->select('stores.id')
                    ->selectRaw('SUM(sales.settlement_amount) as total_sales')
                    ->groupBy('stores.id')
                    ->orderByDesc('total_sales')
                    ->get();

                $lastMonthRank = null;
                foreach ($lastMonthStats as $index => $stat) {
                    if ($stat->id == $storeId) {
                        $lastMonthRank = $index + 1;
                        break;
                    }
                }

                if ($lastMonthRank && $currentRank) {
                    return $lastMonthRank - $currentRank; // 양수면 순위 상승
                }

                return 0;
            });
        } catch (\Exception $e) {
            Log::warning('Rank change calculation failed', [
                'store_id' => $storeId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * 매출 성장률 계산 (전월 대비)
     */
    public function calculateGrowthRate(int $storeId): float
    {
        try {
            $cacheKey = "growth_rate_{$storeId}_" . now()->format('Y-m');

            return Cache::remember($cacheKey, 3600, function () use ($storeId) {
                // 이번달 매출
                $thisMonth = Sale::where('store_id', $storeId)
                    ->whereBetween('sale_date', [now()->startOfMonth(), now()->endOfMonth()])
                    ->sum('settlement_amount');

                // 전월 매출
                $lastMonth = Sale::where('store_id', $storeId)
                    ->whereBetween('sale_date', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
                    ->sum('settlement_amount');

                if ($lastMonth > 0) {
                    return round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1);
                }

                return $thisMonth > 0 ? 100.0 : 0.0; // 전월 데이터 없으면 100% 또는 0%
            });
        } catch (\Exception $e) {
            Log::warning('Growth rate calculation failed', [
                'store_id' => $storeId,
                'error' => $e->getMessage()
            ]);
            return 0.0;
        }
    }

    /**
     * 매장 성과 종합 분석
     */
    public function getStorePerformanceAnalysis(int $storeId): array
    {
        try {
            $store = Store::with('branch')->findOrFail($storeId);

            // 기본 성과 데이터
            $todaySales = Sale::where('store_id', $storeId)
                ->whereDate('sale_date', now()->toDateString())
                ->sum('settlement_amount');

            $monthSales = Sale::where('store_id', $storeId)
                ->whereBetween('sale_date', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('settlement_amount');

            $totalActivations = Sale::where('store_id', $storeId)->count();

            // 순위 계산
            $currentRank = $this->calculateCurrentRank($storeId);

            return [
                'store' => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'code' => $store->code,
                    'branch_name' => $store->branch->name ?? 'Unknown'
                ],
                'performance' => [
                    'today_sales' => (float) $todaySales,
                    'month_sales' => (float) $monthSales,
                    'total_activations' => (int) $totalActivations,
                    'avg_sale_amount' => $totalActivations > 0 ? round($monthSales / $totalActivations) : 0
                ],
                'ranking' => [
                    'current_rank' => $currentRank,
                    'rank_change' => $this->calculateRankChange($storeId, $currentRank)
                ],
                'trends' => [
                    'growth_rate' => $this->calculateGrowthRate($storeId),
                    'performance_trend' => $this->calculateGrowthRate($storeId) > 0 ? 'improving' : 'declining'
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Store performance analysis failed', [
                'store_id' => $storeId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 현재 순위 계산
     */
    private function calculateCurrentRank(int $storeId): ?int
    {
        $rankings = Sale::join('stores', 'sales.store_id', '=', 'stores.id')
            ->whereBetween('sales.sale_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->select('stores.id')
            ->selectRaw('SUM(sales.settlement_amount) as total_sales')
            ->groupBy('stores.id')
            ->orderByDesc('total_sales')
            ->get();

        foreach ($rankings as $index => $ranking) {
            if ($ranking->id == $storeId) {
                return $index + 1;
            }
        }

        return null;
    }
}