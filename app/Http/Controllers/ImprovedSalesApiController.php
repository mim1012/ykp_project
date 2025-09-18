<?php

namespace App\Http\Controllers;

use App\Application\Services\SaleServiceInterface;
use App\Http\Requests\CreateSaleRequest;
use App\Http\Resources\SaleCollection;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImprovedSalesApiController extends Controller
{
    public function __construct(
        private readonly SaleServiceInterface $saleService
    ) {}

    /**
     * 대량 판매 데이터 저장
     */
    public function bulkSave(CreateSaleRequest $request): JsonResponse
    {
        $this->authorize('create', Sale::class);

        try {
            $result = $this->saleService->bulkCreate($request, Auth::user());

            return response()->json($result, 201);

        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            \Log::error('Bulk save error', [
                'message' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '저장 중 오류가 발생했습니다.',
            ], 500);
        }
    }

    /**
     * 판매 데이터 조회
     */
    public function index(Request $request): SaleCollection
    {
        $filters = $request->only([
            'start_date', 'end_date', 'store_id',
            'branch_id', 'per_page',
        ]);

        $sales = $this->saleService->getFilteredSales($filters, Auth::user());

        return new SaleCollection($sales);
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
            \Log::error('Statistics error', [
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
}
