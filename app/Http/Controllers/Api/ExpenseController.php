<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ExpenseController extends Controller
{
    /**
     * Get expenses list
     * GET /api/expenses
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = Expense::with(['store', 'createdBy']);

            // RBAC 필터링
            if ($user->isStore()) {
                $query->where('store_id', $user->store_id);
            } elseif ($user->isBranch()) {
                $query->whereHas('store', function ($q) use ($user) {
                    $q->where('branch_id', $user->branch_id);
                });
            }

            // 날짜 범위 필터
            if ($request->has('start_date')) {
                $query->where('expense_date', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->where('expense_date', '<=', $request->end_date);
            }

            // 월별 필터
            if ($request->has('year') && $request->has('month')) {
                $query->whereYear('expense_date', $request->year)
                      ->whereMonth('expense_date', $request->month);
            }

            // 정렬
            $query->orderBy('expense_date', 'desc');

            $expenses = $query->paginate($request->input('per_page', 30));

            return response()->json([
                'success' => true,
                'data' => $expenses->items(),
                'meta' => [
                    'current_page' => $expenses->currentPage(),
                    'last_page' => $expenses->lastPage(),
                    'per_page' => $expenses->perPage(),
                    'total' => $expenses->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get expenses list', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get expenses list',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new expense
     * POST /api/expenses
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'expense_date' => 'required|date',
                'description' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0',
            ]);

            $user = Auth::user();

            // 매장 사용자만 생성 가능
            if (!$user->isStore()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only store users can create expenses',
                ], 403);
            }

            $validated['store_id'] = $user->store_id;
            $validated['created_by'] = $user->id;

            $expense = Expense::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Expense created successfully',
                'data' => $expense->load(['store', 'createdBy']),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create expense', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create expense',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get expense summary (monthly/yearly)
     * GET /api/expenses/summary
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = Expense::query();

            // RBAC 필터링
            if ($user->isStore()) {
                $query->where('store_id', $user->store_id);
            } elseif ($user->isBranch()) {
                $query->whereHas('store', function ($q) use ($user) {
                    $q->where('branch_id', $user->branch_id);
                });
            }

            // 월별 또는 연도별 합계
            if ($request->has('year') && $request->has('month')) {
                $summary = $query->whereYear('expense_date', $request->year)
                                 ->whereMonth('expense_date', $request->month)
                                 ->selectRaw('COUNT(*) as count, SUM(amount) as total, AVG(amount) as average')
                                 ->first();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'period' => sprintf('%04d-%02d', $request->year, $request->month),
                        'type' => 'monthly',
                        'count' => $summary->count ?? 0,
                        'total' => floatval($summary->total ?? 0),
                        'average' => floatval($summary->average ?? 0),
                    ],
                ]);
            } elseif ($request->has('year')) {
                $summary = $query->whereYear('expense_date', $request->year)
                                 ->selectRaw('
                                     EXTRACT(MONTH FROM expense_date) as month,
                                     COUNT(*) as count,
                                     SUM(amount) as total
                                 ')
                                 ->groupByRaw('EXTRACT(MONTH FROM expense_date)')
                                 ->orderBy('month')
                                 ->get();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'period' => $request->year,
                        'type' => 'yearly',
                        'monthly_data' => $summary,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Please provide year and/or month parameters',
            ], 400);
        } catch (\Exception $e) {
            Log::error('Failed to get expense summary', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get summary',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get expense detail
     * GET /api/expenses/{id}
     */
    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $expense = Expense::with(['store', 'createdBy'])->findOrFail($id);

            // 권한 체크
            if ($user->isStore() && $expense->store_id !== $user->store_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $expense,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get expense detail',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update expense
     * PUT /api/expenses/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $expense = Expense::findOrFail($id);
            $user = Auth::user();

            // 권한 체크: 매장은 자기 것만, 지사/본사는 조회만
            if ($user->isStore()) {
                if ($expense->store_id !== $user->store_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized',
                    ], 403);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Only store users can update expenses',
                ], 403);
            }

            $validated = $request->validate([
                'expense_date' => 'sometimes|required|date',
                'description' => 'sometimes|required|string|max:255',
                'amount' => 'sometimes|required|numeric|min:0',
            ]);

            $expense->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Expense updated successfully',
                'data' => $expense->fresh(['store', 'createdBy']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update expense',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete expense
     * DELETE /api/expenses/{id}
     */
    public function destroy($id): JsonResponse
    {
        try {
            $expense = Expense::findOrFail($id);
            $user = Auth::user();

            // 권한 체크: 매장만 삭제 가능
            if ($user->isStore()) {
                if ($expense->store_id !== $user->store_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized',
                    ], 403);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Only store users can delete expenses',
                ], 403);
            }

            $expense->delete();

            return response()->json([
                'success' => true,
                'message' => 'Expense deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete expense',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
