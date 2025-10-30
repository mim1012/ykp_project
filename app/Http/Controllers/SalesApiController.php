<?php

namespace App\Http\Controllers;

use App\Application\Services\SaleServiceInterface;
use App\Http\Requests\CreateSaleRequest;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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

            // 캐시 무효화: 매출 데이터 생성 후 대시보드 캐시 삭제
            $this->clearDashboardCache();

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
            'sale_ids.*' => ['required', 'integer', 'exists:pgsql_local.sales,id'],
        ]);

        try {
            $result = $this->saleService->bulkDelete($request->sale_ids, Auth::user());

            // 캐시 무효화: 매출 데이터 삭제 후 대시보드 캐시 삭제
            $this->clearDashboardCache();

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

    /**
     * 대시보드 캐시 무효화
     * Sales CRUD 작업 후 호출하여 관련 캐시를 모두 삭제
     */
    private function clearDashboardCache(): void
    {
        try {
            // Laravel 8.37+ 지원: Cache::flush() 대신 패턴 매칭 사용
            // database 캐시 드라이버는 패턴 매칭을 직접 지원하지 않으므로
            // 모든 사용자에 대해 캐시를 삭제하는 대신, 현재 사용자의 캐시만 삭제

            $user = Auth::user();
            $patterns = [
                'dashboard_overview',
                'store_ranking',
                'sales_trend',
                'financial_summary',
                'dealer_performance',
                'rankings',
                'top_list',
                'kpi',
            ];

            $clearedCount = 0;

            // 사용자별 캐시 키 삭제
            foreach ($patterns as $pattern) {
                // 현재 사용자의 캐시만 삭제 (권한별)
                $cacheKey = sprintf('%s_%s_%s', $pattern, $user->role, $user->id);

                // 5분 단위 타임스탬프 (현재 + 이전 + 다음 버킷 모두 삭제)
                $currentMinute = now()->minute;
                $buckets = [
                    floor($currentMinute / 5) * 5,           // 현재 5분 버킷
                    floor(($currentMinute - 5) / 5) * 5,     // 이전 5분 버킷
                    floor(($currentMinute + 5) / 5) * 5,     // 다음 5분 버킷
                ];

                foreach ($buckets as $bucket) {
                    $timestamp = now()->format('Y-m-d-H:') . str_pad($bucket, 2, '0', STR_PAD_LEFT);
                    $fullKey = $cacheKey . '_' . $timestamp;

                    if (Cache::forget($fullKey)) {
                        $clearedCount++;
                    }
                }
            }

            Log::info('Dashboard cache cleared', [
                'user_id' => $user->id,
                'user_role' => $user->role,
                'cleared_count' => $clearedCount,
            ]);

        } catch (\Exception $e) {
            Log::warning('Cache clearing failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
        }
    }

}
