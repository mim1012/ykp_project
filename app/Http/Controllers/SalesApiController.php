<?php

namespace App\Http\Controllers;

use App\Application\Services\SaleServiceInterface;
use App\Http\Requests\CreateSaleRequest;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SalesApiController extends Controller
{
    public function __construct(
        private readonly SaleServiceInterface $saleService
    ) {}

    /**
     * 대량 판매 데이터 저장
     */
    public function bulkSave(CreateSaleRequest $request): JsonResponse
    {
        // 요청 즉시 로깅 - 모든 요청을 캐치
        Log::info('=== BULK SAVE REQUEST RECEIVED ===', [
            'timestamp' => now()->toIso8601String(),
            'user_id' => Auth::id(),
            'method' => $request->method(),
            'url' => $request->url(),
        ]);

        // Policy 체크 제거 - RBAC Middleware에서 이미 처리함
        // $this->authorize('create', Sale::class);

        try {
            // 요청 데이터 로깅 (디버깅용)
            Log::info('Bulk save request', [
                'user_id' => Auth::id(),
                'user_role' => Auth::user()->role,
                'sales_count' => count($request->input('sales', [])),
                'dealer_code' => $request->input('dealer_code'),
                'sample_fields' => array_keys($request->input('sales.0', [])),
            ]);

            $result = $this->saleService->bulkCreate($request, Auth::user());

            return response()->json($result, 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed in bulk save', [
                'user_id' => Auth::id(),
                'errors' => $e->errors(),
                'input' => $request->input(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '입력 데이터가 유효하지 않습니다.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\DomainException $e) {
            Log::warning('Domain exception in bulk save', [
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Bulk save error', [
                'message' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->input(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '저장 중 오류가 발생했습니다.',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? substr($e->getTraceAsString(), 0, 500) : null,
                'line' => $e->getLine(),
                'file' => basename($e->getFile()),
            ], 500);
        }
    }

    /**
     * 판매 데이터 조회
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'start_date', 'end_date', 'sale_date', 'store_id',
            'branch_id', 'per_page', 'dealer_code', 'dealer_name', 'days', 'all_data',
        ]);

        // days 파라미터 처리 (최대 30일로 제한)
        if ($request->has('days')) {
            $filters['days'] = min((int) $request->get('days', 7), 30);
        }

        $sales = $this->saleService->getFilteredSales($filters, Auth::user());

        return response()->json($sales);
    }

    /**
     * 통계 데이터 조회
     */
    public function statistics(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
        ]);

        $startDate = $request->start_date ?? now()->startOfDay()->format('Y-m-d');
        $endDate = $request->end_date ?? now()->endOfDay()->format('Y-m-d');

        try {
            $statistics = $this->saleService->getStatistics($startDate, $endDate, Auth::user());

            return response()->json($statistics);

        } catch (\Exception $e) {
            Log::error('Statistics error', [
                'message' => $e->getMessage(),
                'user_id' => Auth::id(),
                'period' => [$startDate, $endDate],
            ]);

            return response()->json([
                'error' => 'Failed to load statistics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 대량 삭제
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'sale_ids' => ['required', 'array', 'min:1'],
            'sale_ids.*' => ['required', 'integer', 'exists:sales,id'],
        ]);

        try {
            $result = $this->saleService->bulkDelete($request->sale_ids, Auth::user());

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Bulk delete error', [
                'message' => $e->getMessage(),
                'user_id' => Auth::id(),
                'sale_ids' => $request->sale_ids,
            ]);

            return response()->json([
                'success' => false,
                'message' => '삭제 중 오류가 발생했습니다.',
            ], 500);
        }
    }

}
