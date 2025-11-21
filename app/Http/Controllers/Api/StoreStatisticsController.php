<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Models\Sale;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreStatisticsController extends Controller
{
    /**
     * Get store statistics for a specific period
     * GET /api/stores/{id}/statistics?period=daily&date=2025-11-20
     * GET /api/stores/{id}/statistics?period=monthly&year=2025&month=11
     * GET /api/stores/{id}/statistics?period=yearly&year=2025
     */
    public function index(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $store = Store::with('branch')->findOrFail($id);

            // RBAC: Check permissions
            if ($user->isStore() && $store->id !== $user->store_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            if ($user->isBranch() && $store->branch_id !== $user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            $period = $request->input('period', 'monthly');

            switch ($period) {
                case 'daily':
                    $date = $request->input('date', now()->format('Y-m-d'));
                    return $this->getDailyStatistics($store, $date);

                case 'monthly':
                    $year = $request->input('year', now()->year);
                    $month = $request->input('month', now()->month);
                    return $this->getMonthlyStatistics($store, $year, $month);

                case 'yearly':
                    $year = $request->input('year', now()->year);
                    return $this->getYearlyStatistics($store, $year);

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid period. Use: daily, monthly, or yearly',
                    ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Failed to get store statistics', [
                'store_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get daily statistics for a store
     */
    protected function getDailyStatistics(Store $store, string $date): JsonResponse
    {
        $sales = Sale::where('store_id', $store->id)
            ->whereDate('sale_date', $date)
            ->get();

        // Carrier distribution
        $carrierDistribution = $sales->groupBy('carrier')
            ->map(fn ($group) => $group->count())
            ->toArray();

        // Activation type distribution
        $activationTypeDistribution = $sales->groupBy('activation_type')
            ->map(fn ($group) => $group->count())
            ->toArray();

        // Total sales
        $totalSales = $sales->count();
        $totalSettlement = $sales->sum('settlement_amount');

        // Goal achievement (daily goal)
        $goal = Goal::where('store_id', $store->id)
            ->whereYear('target_month', date('Y', strtotime($date)))
            ->whereMonth('target_month', date('m', strtotime($date)))
            ->first();

        $goalAchievement = null;
        if ($goal) {
            $daysInMonth = date('t', strtotime($date));
            $dailyTarget = $goal->sales_target / $daysInMonth;
            $dailyActivationTarget = $goal->activation_target / $daysInMonth;

            $goalAchievement = [
                'daily_sales_target' => round($dailyTarget, 2),
                'daily_sales_actual' => $totalSettlement,
                'daily_sales_achievement_rate' => $dailyTarget > 0
                    ? round(($totalSettlement / $dailyTarget) * 100, 2)
                    : 0,
                'daily_activation_target' => round($dailyActivationTarget, 0),
                'daily_activation_actual' => $totalSales,
                'daily_activation_achievement_rate' => $dailyActivationTarget > 0
                    ? round(($totalSales / $dailyActivationTarget) * 100, 2)
                    : 0,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'period' => 'daily',
                'date' => $date,
                'store' => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'code' => $store->code,
                    'branch_name' => $store->branch->name ?? null,
                ],
                'summary' => [
                    'total_sales' => $totalSales,
                    'total_settlement_amount' => floatval($totalSettlement),
                    'average_settlement_per_sale' => $totalSales > 0
                        ? round($totalSettlement / $totalSales, 2)
                        : 0,
                ],
                'carrier_distribution' => $carrierDistribution,
                'activation_type_distribution' => $activationTypeDistribution,
                'goal_achievement' => $goalAchievement,
            ],
        ]);
    }

    /**
     * Get monthly statistics for a store
     */
    protected function getMonthlyStatistics(Store $store, int $year, int $month): JsonResponse
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        $sales = Sale::where('store_id', $store->id)
            ->whereDate('sale_date', '>=', $startDate)
            ->whereDate('sale_date', '<=', $endDate)
            ->get();

        // Carrier distribution
        $carrierDistribution = $sales->groupBy('carrier')
            ->map(fn ($group) => $group->count())
            ->toArray();

        // Activation type distribution
        $activationTypeDistribution = $sales->groupBy('activation_type')
            ->map(fn ($group) => $group->count())
            ->toArray();

        // Daily breakdown
        $dailyBreakdown = $sales->groupBy(function ($sale) {
            return date('Y-m-d', strtotime($sale->sale_date));
        })
        ->map(function ($group) {
            return [
                'count' => $group->count(),
                'settlement_amount' => floatval($group->sum('settlement_amount')),
            ];
        })
        ->toArray();

        // Total sales
        $totalSales = $sales->count();
        $totalSettlement = $sales->sum('settlement_amount');

        // Goal achievement
        $goal = Goal::where('store_id', $store->id)
            ->whereYear('target_month', $year)
            ->whereMonth('target_month', $month)
            ->first();

        $goalAchievement = null;
        if ($goal) {
            $goalAchievement = [
                'sales_target' => floatval($goal->sales_target),
                'sales_actual' => $totalSettlement,
                'sales_achievement_rate' => $goal->sales_target > 0
                    ? round(($totalSettlement / $goal->sales_target) * 100, 2)
                    : 0,
                'activation_target' => $goal->activation_target,
                'activation_actual' => $totalSales,
                'activation_achievement_rate' => $goal->activation_target > 0
                    ? round(($totalSales / $goal->activation_target) * 100, 2)
                    : 0,
                'margin_target' => floatval($goal->margin_target),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'period' => 'monthly',
                'year' => $year,
                'month' => $month,
                'store' => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'code' => $store->code,
                    'branch_name' => $store->branch->name ?? null,
                ],
                'summary' => [
                    'total_sales' => $totalSales,
                    'total_settlement_amount' => floatval($totalSettlement),
                    'average_settlement_per_sale' => $totalSales > 0
                        ? round($totalSettlement / $totalSales, 2)
                        : 0,
                ],
                'carrier_distribution' => $carrierDistribution,
                'activation_type_distribution' => $activationTypeDistribution,
                'daily_breakdown' => $dailyBreakdown,
                'goal_achievement' => $goalAchievement,
            ],
        ]);
    }

    /**
     * Get yearly statistics for a store
     */
    protected function getYearlyStatistics(Store $store, int $year): JsonResponse
    {
        $startDate = sprintf('%04d-01-01', $year);
        $endDate = sprintf('%04d-12-31', $year);

        $sales = Sale::where('store_id', $store->id)
            ->whereDate('sale_date', '>=', $startDate)
            ->whereDate('sale_date', '<=', $endDate)
            ->get();

        // Carrier distribution
        $carrierDistribution = $sales->groupBy('carrier')
            ->map(fn ($group) => $group->count())
            ->toArray();

        // Activation type distribution
        $activationTypeDistribution = $sales->groupBy('activation_type')
            ->map(fn ($group) => $group->count())
            ->toArray();

        // Monthly breakdown
        $monthlyBreakdown = $sales->groupBy(function ($sale) {
            return date('Y-m', strtotime($sale->sale_date));
        })
        ->map(function ($group) {
            return [
                'count' => $group->count(),
                'settlement_amount' => floatval($group->sum('settlement_amount')),
            ];
        })
        ->toArray();

        // Total sales
        $totalSales = $sales->count();
        $totalSettlement = $sales->sum('settlement_amount');

        // Goal achievement (sum of all monthly goals)
        $goals = Goal::where('store_id', $store->id)
            ->whereYear('target_month', $year)
            ->get();

        $goalAchievement = null;
        if ($goals->isNotEmpty()) {
            $totalSalesTarget = $goals->sum('sales_target');
            $totalActivationTarget = $goals->sum('activation_target');
            $totalMarginTarget = $goals->sum('margin_target');

            $goalAchievement = [
                'yearly_sales_target' => floatval($totalSalesTarget),
                'yearly_sales_actual' => $totalSettlement,
                'yearly_sales_achievement_rate' => $totalSalesTarget > 0
                    ? round(($totalSettlement / $totalSalesTarget) * 100, 2)
                    : 0,
                'yearly_activation_target' => $totalActivationTarget,
                'yearly_activation_actual' => $totalSales,
                'yearly_activation_achievement_rate' => $totalActivationTarget > 0
                    ? round(($totalSales / $totalActivationTarget) * 100, 2)
                    : 0,
                'yearly_margin_target' => floatval($totalMarginTarget),
                'monthly_goals_count' => $goals->count(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'period' => 'yearly',
                'year' => $year,
                'store' => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'code' => $store->code,
                    'branch_name' => $store->branch->name ?? null,
                ],
                'summary' => [
                    'total_sales' => $totalSales,
                    'total_settlement_amount' => floatval($totalSettlement),
                    'average_settlement_per_sale' => $totalSales > 0
                        ? round($totalSettlement / $totalSales, 2)
                        : 0,
                ],
                'carrier_distribution' => $carrierDistribution,
                'activation_type_distribution' => $activationTypeDistribution,
                'monthly_breakdown' => $monthlyBreakdown,
                'goal_achievement' => $goalAchievement,
            ],
        ]);
    }
}
