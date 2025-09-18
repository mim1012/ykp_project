<?php

namespace App\Http\Controllers\Api;

use App\Application\Services\MonthlySettlementService;
use App\Http\Controllers\Controller;
use App\Models\MonthlySettlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 월마감정산 API 컨트롤러
 */
class MonthlySettlementController extends Controller
{
    protected MonthlySettlementService $settlementService;

    public function __construct(MonthlySettlementService $settlementService)
    {
        $this->settlementService = $settlementService;
    }

    /**
     * 월마감정산 목록 조회
     */
    public function index(Request $request): JsonResponse
    {
        $query = MonthlySettlement::with(['dealerProfile', 'confirmedByUser']);

        // 필터링
        if ($request->has('year')) {
            $query->whereYear('year_month', $request->year);
        }

        if ($request->has('year_month')) {
            $query->where('year_month', $request->year_month);
        }

        if ($request->has('dealer_code')) {
            $query->where('dealer_code', $request->dealer_code);
        }

        if ($request->has('status')) {
            $query->where('settlement_status', $request->status);
        }

        // 정렬
        $sortField = $request->get('sort', 'year_month');
        $sortOrder = $request->get('order', 'desc');
        $query->orderBy($sortField, $sortOrder);

        $limit = min($request->get('limit', 20), 100);
        $settlements = $query->paginate($limit);

        return response()->json([
            'success' => true,
            'data' => $settlements->items(),
            'meta' => [
                'pagination' => [
                    'page' => $settlements->currentPage(),
                    'limit' => $settlements->perPage(),
                    'total' => $settlements->total(),
                    'pages' => $settlements->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * 월마감정산 자동 생성/재계산
     */
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'year_month' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'dealer_code' => 'required|string|exists:dealer_profiles,dealer_code',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '입력 데이터가 올바르지 않습니다.',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $settlement = $this->settlementService->generateMonthlySettlement(
                $request->year_month,
                $request->dealer_code
            );

            return response()->json([
                'success' => true,
                'message' => '월마감정산이 생성/업데이트되었습니다.',
                'data' => $settlement,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '월마감정산 생성 실패: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * 전체 대리점 월마감정산 일괄 생성 (월말 처리)
     */
    public function generateAll(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'year_month' => 'required|string|regex:/^\d{4}-\d{2}$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '년월 형식이 올바르지 않습니다.',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $results = $this->settlementService->generateAllDealerSettlements($request->year_month);

            $successCount = collect($results)->where('status', 'success')->count();
            $errorCount = collect($results)->where('status', 'error')->count();

            return response()->json([
                'success' => true,
                'message' => "월마감정산 완료 - 성공: {$successCount}건, 실패: {$errorCount}건",
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '일괄 생성 실패: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * 월마감정산 확정
     */
    public function confirm(Request $request, int $id): JsonResponse
    {
        try {
            $settlement = $this->settlementService->confirmSettlement($id, auth()->id());

            return response()->json([
                'success' => true,
                'message' => '월마감정산이 확정되었습니다.',
                'data' => $settlement,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * 월마감정산 마감 (수정 불가)
     */
    public function close(Request $request, int $id): JsonResponse
    {
        try {
            $settlement = $this->settlementService->closeSettlement($id);

            return response()->json([
                'success' => true,
                'message' => '월마감정산이 마감되었습니다. 더 이상 수정할 수 없습니다.',
                'data' => $settlement,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * 월별 통합 대시보드 데이터
     */
    public function dashboardData(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'year_month' => 'required|string|regex:/^\d{4}-\d{2}$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '년월 형식이 올바르지 않습니다.',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $dashboardData = $this->settlementService->getMonthlyDashboardData($request->year_month);

            return response()->json([
                'success' => true,
                'data' => $dashboardData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '대시보드 데이터 로드 실패: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * 연간 트렌드 분석
     */
    public function yearlyTrend(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|between:2020,2030',
            'dealer_code' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '입력 데이터가 올바르지 않습니다.',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $trendData = $this->settlementService->getYearlyTrend(
                $request->year,
                $request->dealer_code
            );

            return response()->json([
                'success' => true,
                'data' => $trendData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '트렌드 분석 실패: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * 특정 월마감정산 상세 조회
     */
    public function show(int $id): JsonResponse
    {
        try {
            $settlement = MonthlySettlement::with(['dealerProfile', 'confirmedByUser'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $settlement,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '정산 데이터를 찾을 수 없습니다.',
            ], 404);
        }
    }

    /**
     * 월마감정산 수정 (임시저장 상태만)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $settlement = MonthlySettlement::findOrFail($id);

            if (! $settlement->isEditable()) {
                return response()->json([
                    'success' => false,
                    'message' => '마감된 정산은 수정할 수 없습니다.',
                ], 400);
            }

            $settlement->update($request->only([
                'notes',
            ]));

            return response()->json([
                'success' => true,
                'message' => '정산 정보가 수정되었습니다.',
                'data' => $settlement,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '수정 실패: '.$e->getMessage(),
            ], 500);
        }
    }
}
