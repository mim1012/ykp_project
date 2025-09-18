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

            // 디버깅: 요청 데이터 로깅
            Log::info('Bulk create request received', [
                'user_id' => $user->id,
                'user_role' => $user->role,
                'sales_count' => count($request->validated()['sales'] ?? []),
                'dealer_code' => $request->dealer_code ?? 'not_provided',
                'sample_sale' => $request->validated()['sales'][0] ?? null,
            ]);

            try {
                $storeInfo = $this->resolveStoreInfo($request, $user);

                Log::info('Store info resolved successfully', [
                    'user_id' => $user->id,
                    'user_role' => $user->role,
                    'storeInfo' => $storeInfo,
                ]);

                // store_id와 branch_id가 반드시 있는지 다시 확인
                if (! isset($storeInfo['store_id']) || ! $storeInfo['store_id']) {
                    throw new \DomainException('store_id가 resolveStoreInfo에서 설정되지 않았습니다.');
                }
                if (! isset($storeInfo['branch_id']) || ! $storeInfo['branch_id']) {
                    throw new \DomainException('branch_id가 resolveStoreInfo에서 설정되지 않았습니다.');
                }

            } catch (\Exception $e) {
                Log::error('Failed to resolve store info', [
                    'user_id' => $user->id,
                    'user_role' => $user->role,
                    'user_store_id' => $user->store_id,
                    'user_branch_id' => $user->branch_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }

            foreach ($request->validated()['sales'] as $index => $saleData) {
                // JS에서 보낸 필드명을 DB 컬럼명으로 매핑
                $fieldMapping = [
                    'base_price' => 'price_setting',
                    'additional_amount' => 'addon_amount',
                    'cash_activation' => 'paper_cash',
                    'new_mnp_discount' => 'new_mnp_disc',
                    'cash_received' => 'cash_in',
                ];

                foreach ($fieldMapping as $jsField => $dbField) {
                    if (isset($saleData[$jsField])) {
                        $saleData[$dbField] = $saleData[$jsField];
                        unset($saleData[$jsField]);
                    }
                }

                try {
                    $calculatedData = $this->calculateSaleData($saleData);
                } catch (\Exception $e) {
                    Log::error('Failed to calculate sale data', [
                        'index' => $index,
                        'sale_data' => $saleData,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw new \DomainException("판매 데이터 계산 실패 (행 {$index}): ".$e->getMessage());
                }

                // store_id와 branch_id는 절대 덮어쓰지 않도록 명시적으로 제거
                unset($saleData['store_id']);
                unset($saleData['branch_id']);

                // PostgreSQL 호환성을 위한 안전한 생성
                $mergedData = array_merge($saleData, $calculatedData, $storeInfo);

                // 첫 번째 row만 상세 로깅
                if ($index === 0) {
                    Log::info('First row merge details', [
                        'storeInfo' => $storeInfo,
                        'saleData_keys' => array_keys($saleData),
                        'calculatedData_keys' => array_keys($calculatedData),
                        'merged_store_id' => $mergedData['store_id'] ?? 'NULL',
                        'merged_branch_id' => $mergedData['branch_id'] ?? 'NULL',
                    ]);
                }

                // store_id와 branch_id가 반드시 있는지 확인
                if (! isset($mergedData['store_id']) || ! $mergedData['store_id']) {
                    Log::error('store_id is missing!', [
                        'user_id' => $user->id,
                        'user_role' => $user->role,
                        'user_store_id' => $user->store_id,
                        'storeInfo' => $storeInfo,
                        'merged_data' => $mergedData,
                    ]);
                    throw new \DomainException('store_id가 설정되지 않았습니다.');
                }

                if (! isset($mergedData['branch_id']) || ! $mergedData['branch_id']) {
                    Log::error('branch_id is missing!', [
                        'user_id' => $user->id,
                        'user_role' => $user->role,
                        'user_branch_id' => $user->branch_id,
                        'storeInfo' => $storeInfo,
                        'merged_data' => $mergedData,
                    ]);
                    throw new \DomainException('branch_id가 설정되지 않았습니다.');
                }

                // dealer_code 처리 (요청에서 제공되면 사용)
                if ($request->has('dealer_code') && $request->dealer_code) {
                    $mergedData['dealer_code'] = $request->dealer_code;
                }

                // 신규 필드들 처리 (존재하면 저장)
                $newFields = ['dealer_name', 'serial_number', 'customer_name', 'customer_birth_date', 'model_name', 'phone_number', 'salesperson', 'memo'];
                foreach ($newFields as $field) {
                    if (isset($saleData[$field]) && $saleData[$field] !== null && $saleData[$field] !== '') {
                        $mergedData[$field] = $saleData[$field];
                    }
                }

                // 필수 필드 기본값 설정
                $mergedData['created_at'] = now();
                $mergedData['updated_at'] = now();

                // PostgreSQL 호환 방식으로 생성
                try {
                    Log::info('Creating sale record', [
                        'store_id' => $mergedData['store_id'],
                        'branch_id' => $mergedData['branch_id'],
                        'user_role' => $user->role,
                        'sale_date' => $mergedData['sale_date'] ?? 'not_set',
                    ]);

                    Sale::create($mergedData);
                    $savedCount++;
                } catch (\Exception $e) {
                    Log::error('Failed to create sale record', [
                        'index' => $index,
                        'merged_data' => $mergedData,
                        'error' => $e->getMessage(),
                        'sql_error' => $e instanceof \Illuminate\Database\QueryException ? $e->getSql() : 'N/A',
                    ]);
                    throw new \DomainException("판매 데이터 저장 실패 (행 {$index}): ".$e->getMessage());
                }
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
            // 매장 계정은 자신의 store_id를 사용
            if (! $user->store_id) {
                Log::error('Store user has no store_id!', [
                    'user_id' => $user->id,
                    'user_role' => $user->role,
                    'user_data' => $user->toArray(),
                ]);
                throw new \DomainException('매장 계정에 store_id가 설정되지 않았습니다.');
            }

            // store 테이블에서 branch_id 가져오기
            $store = Store::find($user->store_id);
            if (! $store) {
                Log::error('Store not found for user!', [
                    'user_id' => $user->id,
                    'store_id' => $user->store_id,
                ]);
                throw new \DomainException("매장 정보를 찾을 수 없습니다. (store_id: {$user->store_id})");
            }

            $storeInfo = [
                'store_id' => (int) $user->store_id,
                'branch_id' => (int) $store->branch_id,
            ];

            Log::info('Store account resolved store info', [
                'user_id' => $user->id,
                'user_role' => $user->role,
                'resolved_store_id' => $storeInfo['store_id'],
                'resolved_branch_id' => $storeInfo['branch_id'],
                'store_data' => $store->toArray(),
                'user_store_id' => $user->store_id,
                'user_branch_id' => $user->branch_id,
            ]);

            return $storeInfo;
        }

        if ($user->isBranch()) {
            $storeId = $request->store_id ??
                      Store::where('branch_id', $user->branch_id)->first()?->id;

            if (! $storeId) {
                // 지사에 매장이 없으면 에러 (store_id는 필수)
                \Log::error('Branch has no stores for sales creation', [
                    'user_id' => $user->id,
                    'branch_id' => $user->branch_id,
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
        // 특정 날짜 조회 (최우선순위)
        if (isset($filters['sale_date'])) {
            $query->whereDate('sale_date', $filters['sale_date']);

            return;
        }

        // 날짜 범위 조회 (start_date & end_date)
        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->whereBetween('sale_date', [$filters['start_date'], $filters['end_date']]);

            return;
        }

        // days 파라미터 처리 (최근 N일)
        if (isset($filters['days'])) {
            $days = (int) $filters['days'];
            $startDate = now()->subDays($days - 1)->startOfDay();
            $query->where('sale_date', '>=', $startDate);

            return;
        }

        // 개별 start_date 또는 end_date 처리
        if (isset($filters['start_date'])) {
            $query->where('sale_date', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('sale_date', '<=', $filters['end_date']);
        }

        // 아무 날짜 필터도 없으면 기본값: 오늘 데이터만
        if (! isset($filters['start_date']) && ! isset($filters['end_date']) && ! isset($filters['sale_date']) && ! isset($filters['days'])) {
            $query->whereDate('sale_date', now()->toDateString());
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
        if (isset($filters['dealer_code']) && ! empty($filters['dealer_code'])) {
            $query->where('dealer_code', $filters['dealer_code']);
        }

        if (isset($filters['dealer_name']) && ! empty($filters['dealer_name'])) {
            $query->where('dealer_name', 'like', '%'.$filters['dealer_name'].'%');
        }
    }

    /**
     * 대량 삭제
     */
    public function bulkDelete(array $saleIds, User $user): array
    {
        return DB::transaction(function () use ($saleIds, $user) {
            // 권한 확인: 사용자가 접근 가능한 판매 데이터인지 확인
            $query = Sale::whereIn('id', $saleIds);

            // 사용자 권한에 따른 필터링
            if ($user->isStore()) {
                $query->where('store_id', $user->store_id);
            } elseif ($user->isBranch()) {
                $query->where('branch_id', $user->branch_id);
            }
            // 본사는 모든 데이터 삭제 가능

            // 삭제 가능한 레코드 수 확인
            $deletableCount = $query->count();

            if ($deletableCount === 0) {
                return [
                    'success' => false,
                    'message' => '삭제할 권한이 있는 데이터가 없습니다.',
                    'deleted_count' => 0,
                ];
            }

            // 삭제 실행
            $deletedCount = $query->delete();

            Log::info('Bulk sales deleted', [
                'user_id' => $user->id,
                'requested_ids' => $saleIds,
                'deleted_count' => $deletedCount,
                'user_role' => $user->role,
            ]);

            return [
                'success' => true,
                'message' => "{$deletedCount}개의 판매 데이터가 삭제되었습니다.",
                'deleted_count' => $deletedCount,
            ];
        });
    }
}
