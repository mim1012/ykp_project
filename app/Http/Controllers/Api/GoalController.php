<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GoalController extends Controller
{
    /**
     * Get current month goal for the authenticated store user
     * GET /api/my-goal
     */
    public function show(): JsonResponse
    {
        try {
            $user = Auth::user();

            // Only store users can access their goals
            if (! $user->isStore() || ! $user->store_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only store users can access goals',
                ], 403);
            }

            $startOfMonth = now()->startOfMonth();
            $endOfMonth = now()->endOfMonth();

            // Get current month goal
            $goal = Goal::where('target_type', 'store')
                ->where('target_id', $user->store_id)
                ->where('period_type', 'monthly')
                ->where('period_start', $startOfMonth->format('Y-m-d'))
                ->where('is_active', true)
                ->first();

            // Calculate current month stats for this store
            $salesStats = Sale::where('store_id', $user->store_id)
                ->whereBetween('sale_date', [$startOfMonth, $endOfMonth])
                ->selectRaw('
                    COALESCE(SUM(settlement_amount), 0) as total_sales,
                    COUNT(*) as total_activations,
                    CASE WHEN COALESCE(SUM(total_rebate), 0) > 0
                        THEN ROUND((COALESCE(SUM(settlement_amount), 0) / COALESCE(SUM(total_rebate), 1)) * 100, 1)
                        ELSE 0
                    END as margin_rate
                ')
                ->first();

            $currentSales = (float) ($salesStats->total_sales ?? 0);
            $currentActivations = (int) ($salesStats->total_activations ?? 0);
            $currentMarginRate = (float) ($salesStats->margin_rate ?? 0);

            // Calculate achievement rates
            $salesAchievementRate = 0;
            $activationAchievementRate = 0;
            $marginAchievementRate = 0;

            if ($goal) {
                if ($goal->sales_target > 0) {
                    $salesAchievementRate = round(($currentSales / $goal->sales_target) * 100, 1);
                }
                if ($goal->activation_target > 0) {
                    $activationAchievementRate = round(($currentActivations / $goal->activation_target) * 100, 1);
                }
                if ($goal->margin_target > 0) {
                    $marginAchievementRate = round(($currentMarginRate / $goal->margin_target) * 100, 1);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'goal' => $goal,
                    'current_sales' => $currentSales,
                    'current_activations' => $currentActivations,
                    'current_margin_rate' => $currentMarginRate,
                    'achievement_rate' => $salesAchievementRate,
                    'activation_achievement_rate' => $activationAchievementRate,
                    'margin_achievement_rate' => $marginAchievementRate,
                    'month' => $startOfMonth->format('Y-m'),
                    'days_remaining' => now()->diffInDays($endOfMonth),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get goal', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get goal',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create or update current month goal (upsert)
     * POST /api/my-goal
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Only store users can set their goals
            if (! $user->isStore() || ! $user->store_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only store users can set goals',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'sales_target' => 'nullable|numeric|min:0',
                'activation_target' => 'nullable|integer|min:0',
                'margin_target' => 'nullable|numeric|min:0|max:100',
                'notes' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $startOfMonth = now()->startOfMonth();
            $endOfMonth = now()->endOfMonth();

            DB::beginTransaction();

            // Upsert: Update existing or create new
            $goal = Goal::updateOrCreate(
                [
                    'target_type' => 'store',
                    'target_id' => $user->store_id,
                    'period_type' => 'monthly',
                    'period_start' => $startOfMonth->format('Y-m-d'),
                ],
                [
                    'period_end' => $endOfMonth->format('Y-m-d'),
                    'sales_target' => $request->sales_target ?? 0,
                    'activation_target' => $request->activation_target,
                    'margin_target' => $request->margin_target,
                    'notes' => $request->notes,
                    'created_by' => $user->id,
                    'is_active' => true,
                ]
            );

            DB::commit();

            // Calculate current achievement stats
            $salesStats = Sale::where('store_id', $user->store_id)
                ->whereBetween('sale_date', [$startOfMonth, $endOfMonth])
                ->selectRaw('
                    COALESCE(SUM(settlement_amount), 0) as total_sales,
                    COUNT(*) as total_activations,
                    CASE WHEN COALESCE(SUM(total_rebate), 0) > 0
                        THEN ROUND((COALESCE(SUM(settlement_amount), 0) / COALESCE(SUM(total_rebate), 1)) * 100, 1)
                        ELSE 0
                    END as margin_rate
                ')
                ->first();

            $currentSales = (float) ($salesStats->total_sales ?? 0);
            $currentActivations = (int) ($salesStats->total_activations ?? 0);
            $currentMarginRate = (float) ($salesStats->margin_rate ?? 0);

            $salesAchievementRate = $goal->sales_target > 0
                ? round(($currentSales / $goal->sales_target) * 100, 1) : 0;
            $activationAchievementRate = $goal->activation_target > 0
                ? round(($currentActivations / $goal->activation_target) * 100, 1) : 0;
            $marginAchievementRate = $goal->margin_target > 0
                ? round(($currentMarginRate / $goal->margin_target) * 100, 1) : 0;

            return response()->json([
                'success' => true,
                'message' => 'Goal saved successfully',
                'data' => [
                    'goal' => $goal,
                    'current_sales' => $currentSales,
                    'current_activations' => $currentActivations,
                    'current_margin_rate' => $currentMarginRate,
                    'achievement_rate' => $salesAchievementRate,
                    'activation_achievement_rate' => $activationAchievementRate,
                    'margin_achievement_rate' => $marginAchievementRate,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to save goal', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save goal',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
