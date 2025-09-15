<?php

namespace App\Application\Services;

use App\Helpers\SalesCalculator;
use App\Http\Requests\CreateSaleRequest;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleService implements SaleServiceInterface
{
    public function bulkCreate(CreateSaleRequest $request, User $user): array
    {
        return DB::transaction(function () use ($request, $user) {
            $savedCount = 0;
            $storeInfo = $this->resolveStoreInfo($request, $user);

            foreach ($request->validated()['sales'] as $saleData) {
                $calculatedData = $this->calculateSaleData($saleData);

                // PostgreSQL 호환성을 위한 안전한 생성
                $mergedData = array_merge($storeInfo, $saleData, $calculatedData);

                // dealer_code 처리 (요청에서 제공되면 사용)
                if ($request->has('dealer_code') && $request->dealer_code) {
                    $mergedData['dealer_code'] = $request->dealer_code;
                }

                // 신규 필드들 처리 (존재하면 저장)
                $newFields = ['dealer_name', 'serial_number', 'customer_name', 'customer_birth_date'];
                foreach ($newFields as $field) {
                    if (isset($saleData[$field]) && $saleData[$field] !== null) {
                        $mergedData[$field] = $saleData[$field];
                    }
                }

                // 필수 필드 기본값 설정
                $mergedData['created_at'] = now();
                $mergedData['updated_at'] = now();

                // PostgreSQL 호환 방식으로 생성
                Sale::create($mergedData);
                $savedCount++;
            }

            Log::info('Bulk sales created', [
                'user_id' => $user->id,
                'count' => $savedCount,
                'store_id' => $storeInfo['store_id'],
            ]);

            return [
                'success' => true,
                'message' => "{$savedCount}개의 판매 데이터가 저장되었습니다.",
                'saved_count' => $savedCount,
            ];
        });
    }

    public function getFilteredSales(array $filters, User $user)
    {
        $query = Sale::with(['store', 'branch']);

        $this->applyUserPermissionFilters($query, $user);
        $this->applyDateFilters($query, $filters);
        $this->applyStoreFilters($query, $filters, $user);
        $this->applyDealerFilters($query, $filters);

        return $query->orderBy('sale_date', 'desc')
            ->paginate($filters['per_page'] ?? 50);
    }

    public function getStatistics(string $startDate, string $endDate, User $user): array
    {
        $baseQuery = Sale::query();

        $this->applyUserPermissionFilters($baseQuery, $user);
        $baseQuery->whereBetween('sale_date', [$startDate, $endDate]);

        if (! $baseQuery->exists()) {
            return $this->getEmptyStatistics($startDate, $endDate);
        }

        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'summary' => $this->calculateSummary($baseQuery),
            'by_carrier' => $this->getCarrierStats($baseQuery),
            'by_activation_type' => $this->getActivationTypeStats($baseQuery),
            'user_context' => [
                'role' => $user->role,
                'accessible_stores' => $user->getAccessibleStoreIds(),
            ],
        ];
    }

    private function resolveStoreInfo(CreateSaleRequest $request, User $user): array
    {
        if ($user->isStore()) {
            return [
                'store_id' => $user->store_id,
                'branch_id' => $user->branch_id,
            ];
        }

        if ($user->isBranch()) {
            $storeId = $request->store_id ??
                      Store::where('branch_id', $user->branch_id)->first()?->id;

            if (! $storeId) {
                // 지사에 매장이 없으면 에러 (store_id는 필수)
                \Log::error('Branch has no stores for sales creation', [
                    'user_id' => $user->id,
                    'branch_id' => $user->branch_id
                ]);

                throw new \DomainException('지사에 등록된 매장이 없습니다. 먼저 매장을 생성해주세요.');
            }

            return [
                'store_id' => $storeId,
                'branch_id' => $user->branch_id,
            ];
        }

        // 본사
        if (! $request->store_id || ! $request->branch_id) {
            throw new \DomainException('본사는 매장과 지사 정보를 모두 제공해야 합니다.');
        }

        return [
            'store_id' => $request->store_id,
            'branch_id' => $request->branch_id,
        ];
    }

    private function calculateSaleData(array $saleData): array
    {
        $calculated = SalesCalculator::computeRow($saleData);

        return [
            'rebate_total' => $calculated['total_rebate'],
            'settlement_amount' => $calculated['settlement'],
            'tax' => $calculated['tax'],
            'margin_before_tax' => $calculated['margin_before'],
            'margin_after_tax' => $calculated['margin_after'],
        ];
    }

    private function applyUserPermissionFilters($query, User $user): void
    {
        if ($user->isStore()) {
            $query->where('store_id', $user->store_id);
        } elseif ($user->isBranch()) {
            $query->where('branch_id', $user->branch_id);
        }
        // 본사는 모든 데이터 접근 가능
    }

    private function applyDateFilters($query, array $filters): void
    {
        // 특정 날짜 조회 (우선순위)
        if (isset($filters['sale_date'])) {
            $query->whereDate('sale_date', $filters['sale_date']);
            return;
        }
        
        // 기간 조회
        if (isset($filters['start_date'])) {
            $query->where('sale_date', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('sale_date', '<=', $filters['end_date']);
        }
    }

    private function applyStoreFilters($query, array $filters, User $user): void
    {
        if (isset($filters['store_id'])) {
            $accessibleStoreIds = $user->getAccessibleStoreIds();

            if (in_array($filters['store_id'], $accessibleStoreIds)) {
                $query->where('store_id', $filters['store_id']);
            }
        }
    }

    private function getEmptyStatistics(string $startDate, string $endDate): array
    {
        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'summary' => (object) [
                'total_count' => 0,
                'total_settlement' => 0,
                'total_tax' => 0,
                'total_margin' => 0,
                'avg_settlement' => 0,
                'active_stores' => 0,
            ],
            'by_carrier' => [],
            'by_activation_type' => [],
            'message' => '선택한 기간에 데이터가 없습니다.',
        ];
    }

    private function calculateSummary($baseQuery): object
    {
        return (clone $baseQuery)
            ->selectRaw('
                COUNT(*) as total_count,
                COALESCE(SUM(settlement_amount), 0) as total_settlement,
                COALESCE(SUM(tax), 0) as total_tax,
                COALESCE(SUM(margin_after_tax), 0) as total_margin,
                COALESCE(AVG(settlement_amount), 0) as avg_settlement,
                COUNT(DISTINCT store_id) as active_stores
            ')
            ->first();
    }

    private function getCarrierStats($baseQuery): \Illuminate\Support\Collection
    {
        return (clone $baseQuery)
            ->select('carrier')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(settlement_amount), 0) as total')
            ->groupBy('carrier')
            ->get();
    }

    private function getActivationTypeStats($baseQuery): \Illuminate\Support\Collection
    {
        return (clone $baseQuery)
            ->select('activation_type')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(settlement_amount), 0) as total')
            ->groupBy('activation_type')
            ->get();
    }

    /**
     * dealer_code 필터링 적용
     */
    private function applyDealerFilters($query, array $filters): void
    {
        if (isset($filters['dealer_code']) && !empty($filters['dealer_code'])) {
            $query->where('dealer_code', $filters['dealer_code']);
        }

        if (isset($filters['dealer_name']) && !empty($filters['dealer_name'])) {
            $query->where('dealer_name', 'like', '%' . $filters['dealer_name'] . '%');
        }
    }
}
