<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RefundController extends Controller
{
    /**
     * 환수금액 목록 조회
     */
    public function index(Request $request): JsonResponse
    {
        $query = Refund::with(['dealerProfile', 'originalSale']);

        // 필터링
        if ($request->has('dealer_code')) {
            $query->where('dealer_code', $request->dealer_code);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('refund_date', [$request->start_date, $request->end_date]);
        }

        if ($request->has('refund_reason')) {
            $query->where('refund_reason', $request->refund_reason);
        }

        // 정렬
        $sortField = $request->get('sort', 'refund_date');
        $sortOrder = $request->get('order', 'desc');
        $query->orderBy($sortField, $sortOrder);

        // 페이지네이션
        $limit = min($request->get('limit', 20), 100);
        $refunds = $query->paginate($limit);

        return response()->json([
            'success' => true,
            'data' => $refunds->items(),
            'meta' => [
                'pagination' => [
                    'page' => $refunds->currentPage(),
                    'limit' => $refunds->perPage(),
                    'total' => $refunds->total(),
                    'pages' => $refunds->lastPage(),
                ],
                'timestamp' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * 환수금액 등록
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'refund_date' => 'required|date',
            'dealer_code' => 'required|string|max:20',
            'activation_id' => 'nullable|exists:sales,id',
            'customer_name' => 'required|string|max:50',
            'customer_phone' => 'required|string|max:20',
            'refund_reason' => 'required|string|max:100',
            'refund_type' => 'required|string|max:20',
            'original_amount' => 'required|numeric|min:0',
            'refund_amount' => 'required|numeric|min:0',
            'penalty_amount' => 'nullable|numeric|min:0',
            'refund_method' => 'nullable|string|max:20',
            'processed_by' => 'nullable|string|max:50',
            'memo' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $refund = Refund::create($validator->validated());

        return response()->json([
            'success' => true,
            'data' => $refund->load(['dealerProfile', 'originalSale']),
            'message' => '환수금액이 등록되었습니다.',
        ], 201);
    }

    /**
     * 환수금액 단건 조회
     */
    public function show(string $id): JsonResponse
    {
        $refund = Refund::with(['dealerProfile', 'originalSale'])->find($id);

        if (! $refund) {
            return response()->json([
                'success' => false,
                'message' => '환수금액 내역을 찾을 수 없습니다.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $refund,
        ]);
    }

    /**
     * 환수금액 수정
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $refund = Refund::find($id);

        if (! $refund) {
            return response()->json([
                'success' => false,
                'message' => '환수금액 내역을 찾을 수 없습니다.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'refund_date' => 'sometimes|date',
            'dealer_code' => 'sometimes|string|max:20',
            'activation_id' => 'nullable|exists:sales,id',
            'customer_name' => 'sometimes|string|max:50',
            'customer_phone' => 'sometimes|string|max:20',
            'refund_reason' => 'sometimes|string|max:100',
            'refund_type' => 'sometimes|string|max:20',
            'original_amount' => 'sometimes|numeric|min:0',
            'refund_amount' => 'sometimes|numeric|min:0',
            'penalty_amount' => 'nullable|numeric|min:0',
            'refund_method' => 'nullable|string|max:20',
            'processed_by' => 'nullable|string|max:50',
            'memo' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $refund->update($validator->validated());

        return response()->json([
            'success' => true,
            'data' => $refund->load(['dealerProfile', 'originalSale']),
            'message' => '환수금액이 수정되었습니다.',
        ]);
    }

    /**
     * 환수금액 삭제
     */
    public function destroy(string $id): JsonResponse
    {
        $refund = Refund::find($id);

        if (! $refund) {
            return response()->json([
                'success' => false,
                'message' => '환수금액 내역을 찾을 수 없습니다.',
            ], 404);
        }

        $refund->delete();

        return response()->json([
            'success' => true,
            'message' => '환수금액이 삭제되었습니다.',
        ]);
    }

    /**
     * 환수율 분석
     */
    public function analysis(Request $request): JsonResponse
    {
        $yearMonth = $request->get('year_month', now()->format('Y-m'));
        $dealerCode = $request->get('dealer_code');

        $query = Refund::whereYear('refund_date', substr($yearMonth, 0, 4))
            ->whereMonth('refund_date', substr($yearMonth, 5, 2));

        if ($dealerCode) {
            $query->where('dealer_code', $dealerCode);
        }

        $totalRefunds = $query->count();
        $totalAmount = $query->sum('refund_amount');
        $totalPenalty = $query->sum('penalty_amount');
        $netLoss = $totalAmount - $totalPenalty;

        // 환수 원인별 분석
        $reasonBreakdown = $query->selectRaw('refund_reason, COUNT(*) as count, SUM(refund_amount) as total_amount')
            ->groupBy('refund_reason')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'year_month' => $yearMonth,
                'total_refunds' => $totalRefunds,
                'total_amount' => $totalAmount,
                'total_penalty' => $totalPenalty,
                'net_loss' => $netLoss,
                'reason_breakdown' => $reasonBreakdown,
                'average_refund' => $totalRefunds > 0 ? round($totalAmount / $totalRefunds, 2) : 0,
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }
}
