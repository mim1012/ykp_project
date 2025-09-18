<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DailyExpenseController extends Controller
{
    /**
     * 일일지출 목록 조회
     */
    public function index(Request $request): JsonResponse
    {
        $query = DailyExpense::with('dealerProfile');

        // 필터링
        if ($request->has('dealer_code')) {
            $query->where('dealer_code', $request->dealer_code);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('expense_date', [$request->start_date, $request->end_date]);
        }

        // 정렬
        $sortField = $request->get('sort', 'expense_date');
        $sortOrder = $request->get('order', 'desc');
        $query->orderBy($sortField, $sortOrder);

        // 페이지네이션
        $limit = min($request->get('limit', 20), 100);
        $expenses = $query->paginate($limit);

        return response()->json([
            'success' => true,
            'data' => $expenses->items(),
            'meta' => [
                'pagination' => [
                    'page' => $expenses->currentPage(),
                    'limit' => $expenses->perPage(),
                    'total' => $expenses->total(),
                    'pages' => $expenses->lastPage(),
                ],
                'timestamp' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * 일일지출 등록
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'expense_date' => 'required|date',
            'dealer_code' => 'required|string|max:20',
            'category' => 'required|string|max:50',
            'description' => 'nullable|string|max:200',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'nullable|string|max:20',
            'receipt_number' => 'nullable|string|max:50',
            'approved_by' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $expense = DailyExpense::create($validator->validated());

        return response()->json([
            'success' => true,
            'data' => $expense->load('dealerProfile'),
            'message' => '일일지출이 등록되었습니다.',
        ], 201);
    }

    /**
     * 일일지출 단건 조회
     */
    public function show(string $id): JsonResponse
    {
        $expense = DailyExpense::with('dealerProfile')->find($id);

        if (! $expense) {
            return response()->json([
                'success' => false,
                'message' => '일일지출 내역을 찾을 수 없습니다.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $expense,
        ]);
    }

    /**
     * 일일지출 수정
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $expense = DailyExpense::find($id);

        if (! $expense) {
            return response()->json([
                'success' => false,
                'message' => '일일지출 내역을 찾을 수 없습니다.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'expense_date' => 'sometimes|date',
            'dealer_code' => 'sometimes|string|max:20',
            'category' => 'sometimes|string|max:50',
            'description' => 'nullable|string|max:200',
            'amount' => 'sometimes|numeric|min:0',
            'payment_method' => 'nullable|string|max:20',
            'receipt_number' => 'nullable|string|max:50',
            'approved_by' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $expense->update($validator->validated());

        return response()->json([
            'success' => true,
            'data' => $expense->load('dealerProfile'),
            'message' => '일일지출이 수정되었습니다.',
        ]);
    }

    /**
     * 일일지출 삭제
     */
    public function destroy(string $id): JsonResponse
    {
        $expense = DailyExpense::find($id);

        if (! $expense) {
            return response()->json([
                'success' => false,
                'message' => '일일지출 내역을 찾을 수 없습니다.',
            ], 404);
        }

        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => '일일지출이 삭제되었습니다.',
        ]);
    }

    /**
     * 월별 지출 현황 요약
     */
    public function monthlySummary(Request $request): JsonResponse
    {
        $yearMonth = $request->get('year_month', now()->format('Y-m'));
        $dealerCode = $request->get('dealer_code');

        $query = DailyExpense::whereYear('expense_date', substr($yearMonth, 0, 4))
            ->whereMonth('expense_date', substr($yearMonth, 5, 2));

        if ($dealerCode) {
            $query->where('dealer_code', $dealerCode);
        }

        $totalAmount = $query->sum('amount');
        $expenseCount = $query->count();

        $categoryBreakdown = $query->selectRaw('category, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('category')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'year_month' => $yearMonth,
                'total_amount' => $totalAmount,
                'expense_count' => $expenseCount,
                'category_breakdown' => $categoryBreakdown,
                'average_amount' => $expenseCount > 0 ? round($totalAmount / $expenseCount, 2) : 0,
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }
}
