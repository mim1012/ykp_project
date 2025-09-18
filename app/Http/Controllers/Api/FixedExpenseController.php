<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FixedExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FixedExpenseController extends Controller
{
    /**
     * 고정지출 목록 조회
     */
    public function index(Request $request): JsonResponse
    {
        $query = FixedExpense::with('dealerProfile');

        // 필터링
        if ($request->has('year_month')) {
            $query->where('year_month', $request->year_month);
        }

        if ($request->has('dealer_code')) {
            $query->where('dealer_code', $request->dealer_code);
        }

        if ($request->has('expense_type')) {
            $query->where('expense_type', $request->expense_type);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // 정렬
        $sortField = $request->get('sort', 'year_month');
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
     * 고정지출 등록
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'year_month' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'dealer_code' => 'required|string|max:20',
            'expense_type' => 'required|string|max:50',
            'description' => 'nullable|string|max:200',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'nullable|date',
            'payment_date' => 'nullable|date',
            'payment_status' => 'sometimes|in:pending,paid,overdue',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $expense = FixedExpense::create($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $expense->load('dealerProfile'),
                'message' => '고정지출이 등록되었습니다.',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '고정지출 등록 중 오류가 발생했습니다: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * 고정지출 단건 조회
     */
    public function show(string $id): JsonResponse
    {
        $expense = FixedExpense::with('dealerProfile')->find($id);

        if (! $expense) {
            return response()->json([
                'success' => false,
                'message' => '고정지출 내역을 찾을 수 없습니다.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $expense,
        ]);
    }

    /**
     * 고정지출 수정
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $expense = FixedExpense::find($id);

        if (! $expense) {
            return response()->json([
                'success' => false,
                'message' => '고정지출 내역을 찾을 수 없습니다.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'year_month' => 'sometimes|string|regex:/^\d{4}-\d{2}$/',
            'dealer_code' => 'sometimes|string|max:20',
            'expense_type' => 'sometimes|string|max:50',
            'description' => 'nullable|string|max:200',
            'amount' => 'sometimes|numeric|min:0',
            'due_date' => 'nullable|date',
            'payment_date' => 'nullable|date',
            'payment_status' => 'sometimes|in:pending,paid,overdue',
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
            'message' => '고정지출이 수정되었습니다.',
        ]);
    }

    /**
     * 고정지출 삭제
     */
    public function destroy(string $id): JsonResponse
    {
        $expense = FixedExpense::find($id);

        if (! $expense) {
            return response()->json([
                'success' => false,
                'message' => '고정지출 내역을 찾을 수 없습니다.',
            ], 404);
        }

        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => '고정지출이 삭제되었습니다.',
        ]);
    }

    /**
     * 지급 상태 업데이트
     */
    public function updatePaymentStatus(Request $request, string $id): JsonResponse
    {
        $expense = FixedExpense::find($id);

        if (! $expense) {
            return response()->json([
                'success' => false,
                'message' => '고정지출 내역을 찾을 수 없습니다.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'payment_status' => 'required|in:pending,paid,overdue',
            'payment_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->payment_status === 'paid') {
            $expense->markAsPaid($request->payment_date);
        } else {
            $expense->update($validator->validated());
        }

        return response()->json([
            'success' => true,
            'data' => $expense->load('dealerProfile'),
            'message' => '지급 상태가 업데이트되었습니다.',
        ]);
    }

    /**
     * 지급 예정 내역 조회
     */
    public function upcomingPayments(Request $request): JsonResponse
    {
        $days = $request->get('days', 30); // 기본 30일
        $endDate = now()->addDays($days);

        $upcomingPayments = FixedExpense::with('dealerProfile')
            ->where('payment_status', 'pending')
            ->whereBetween('due_date', [now()->toDateString(), $endDate->toDateString()])
            ->orderBy('due_date')
            ->get();

        $totalAmount = $upcomingPayments->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'upcoming_payments' => $upcomingPayments,
                'total_amount' => $totalAmount,
                'count' => $upcomingPayments->count(),
                'period' => [
                    'start_date' => now()->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'days' => $days,
                ],
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }
}
