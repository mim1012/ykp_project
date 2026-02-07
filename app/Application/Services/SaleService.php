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
    private CustomerService $customerService;

    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

    public function bulkCreate(CreateSaleRequest $request, User $user): array
    {
        return DB::transaction(function () use ($request, $user) {
            $savedCount = 0;
            $idMappings = []; // 임시 ID → 실제 DB ID 매핑

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
                // JS 필드명과 DB 컬럼명이 일치하므로 매핑 불필요

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
                $newFields = [
                    'dealer_name', 'serial_number', 'customer_name', 'customer_birth_date',
                    'model_name', 'phone_number', 'salesperson', 'memo',
                    'visit_path', 'customer_address',  // 방문경로, 주소 추가
                    'verbal1_memo', 'verbal2_memo',    // 구두1/2 메모 추가
                ];
                foreach ($newFields as $field) {
                    if (isset($saleData[$field]) && $saleData[$field] !== null && $saleData[$field] !== '') {
                        $mergedData[$field] = $saleData[$field];
                    }
                }

                // carrier와 activation_type 빈 문자열을 NULL로 변환
                if (isset($mergedData['carrier']) && $mergedData['carrier'] === '') {
                    $mergedData['carrier'] = null;
                }
                if (isset($mergedData['activation_type']) && $mergedData['activation_type'] === '') {
                    $mergedData['activation_type'] = null;
                }
                if (isset($mergedData['model_name']) && $mergedData['model_name'] === '') {
                    $mergedData['model_name'] = null;
                }

                // PostgreSQL 호환 방식으로 생성 또는 업데이트
                try {
                    // ID 존재 여부로 UPDATE/INSERT 판단
                    // 임시 ID(문자열 포함 또는 매우 큰 숫자)는 실제 DB ID가 아니므로 INSERT 처리
                    $hasRealId = isset($saleData['id'])
                        && $saleData['id']
                        && is_numeric($saleData['id'])
                        && $saleData['id'] < 9999999999; // 임시 ID는 Date.now() 기반이므로 10자리 이상 (예: 1730000000000)

                    Log::info('Processing sale record', [
                        'row_index' => $index,
                        'has_real_id' => $hasRealId,
                        'id' => $saleData['id'] ?? 'null',
                        'id_type' => isset($saleData['id']) ? gettype($saleData['id']) : 'not_set',
                        'is_numeric' => isset($saleData['id']) ? is_numeric($saleData['id']) : false,
                        'is_temp_id' => isset($saleData['id']) && is_numeric($saleData['id']) && $saleData['id'] >= 9999999999,
                        'store_id' => $mergedData['store_id'],
                        'branch_id' => $mergedData['branch_id'],
                        'user_role' => $user->role,
                        'sale_date' => $mergedData['sale_date'] ?? 'not_set',
                        'customer_name' => $mergedData['customer_name'] ?? 'not_set',
                        'action' => $hasRealId ? 'UPDATE' : 'INSERT'
                    ]);

                    // 실제 DB ID가 있으면 업데이트, 없으면 생성
                    if ($hasRealId) {
                        // UPDATE 시도
                        unset($mergedData['created_at']);
                        $mergedData['updated_at'] = now();

                        // 기존 레코드 확인
                        $existingRecord = Sale::where('id', $saleData['id'])->first();
                        if ($existingRecord) {
                            Log::info("Found existing record for UPDATE", [
                                'id' => $saleData['id'],
                                'existing_store_id' => $existingRecord->store_id,
                                'request_store_id' => $mergedData['store_id'],
                                'store_id_match' => $existingRecord->store_id == $mergedData['store_id']
                            ]);

                            // store_id가 일치하지 않으면 기존 레코드의 store_id 유지
                            if ($existingRecord->store_id != $mergedData['store_id']) {
                                Log::warning("store_id mismatch - keeping existing store_id", [
                                    'existing_store_id' => $existingRecord->store_id,
                                    'request_store_id' => $mergedData['store_id']
                                ]);
                                $mergedData['store_id'] = $existingRecord->store_id;
                                $mergedData['branch_id'] = $existingRecord->branch_id;
                            }
                        } else {
                            Log::warning("Record not found for UPDATE - will INSERT instead", [
                                'id' => $saleData['id'],
                                'store_id' => $mergedData['store_id']
                            ]);
                        }

                        // UPDATE 실행 (ID만으로 조회 - store_id 체크 제거)
                        $updatedCount = Sale::where('id', $saleData['id'])
                            ->update($mergedData);

                        if ($updatedCount > 0) {
                            Log::info("UPDATE SUCCESS - ID: {$saleData['id']}", [
                                'updated_fields_count' => count($mergedData),
                                'updated_count' => $updatedCount
                            ]);
                        } else {
                            Log::warning("UPDATE FAILED - No rows updated for ID: {$saleData['id']}", [
                                'reason' => 'ID not found in database',
                                'sale_id' => $saleData['id']
                            ]);
                        }
                    } else {
                        // CREATE 시에는 created_at과 updated_at 모두 설정
                        $mergedData['created_at'] = now();
                        $mergedData['updated_at'] = now();
                        $newRecord = Sale::create($mergedData);

                        // 가망고객 -> 개통고객 자동 전환 (전화번호 매칭)
                        if ($newRecord->phone_number) {
                            $convertedCustomer = $this->customerService->autoLinkProspectToSale($newRecord);
                            if ($convertedCustomer) {
                                Log::info("Prospect converted to activated customer", [
                                    'customer_id' => $convertedCustomer->id,
                                    'sale_id' => $newRecord->id,
                                    'phone_number' => $newRecord->phone_number,
                                ]);
                            }
                        }

                        // 임시 ID가 있으면 실제 DB ID와 매핑
                        $originalId = $saleData['id'] ?? null;
                        if ($originalId) {
                            $idMappings[$originalId] = $newRecord->id;
                            Log::info("ID Mapping created", [
                                'temp_id' => $originalId,
                                'real_id' => $newRecord->id
                            ]);
                        }

                        Log::info("INSERT SUCCESS - New record created", [
                            'new_id' => $newRecord->id,
                            'original_id' => $originalId ?? 'none',
                            'store_id' => $newRecord->store_id,
                            'sale_date' => $newRecord->sale_date,
                            'customer_name' => $newRecord->customer_name ?? 'not_set'
                        ]);
                    }
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
                'id_mappings_count' => count($idMappings),
                'id_mappings' => $idMappings, // 실제 매핑 내용 로깅
            ]);

            $response = [
                'success' => true,
                'message' => "{$savedCount}개의 판매 데이터가 저장되었습니다.",
                'saved_count' => $savedCount,
                'id_mappings' => $idMappings, // 임시 ID → 실제 DB ID 매핑 반환
            ];

            Log::info('Returning response', [
                'has_id_mappings' => !empty($idMappings),
                'id_mappings_keys' => array_keys($idMappings),
                'response' => $response,
            ]);

            return $response;
        });
    }

    public function getFilteredSales(array $filters, User $user)
    {
        $query = Sale::with(['store', 'branch']);

        $this->applyUserPermissionFilters($query, $user);
        $this->applyDateFilters($query, $filters);
        $this->applyStoreFilters($query, $filters, $user);
        $this->applyDealerFilters($query, $filters);

        return $query->orderBy('sale_date', 'asc')
            ->orderBy('id', 'asc')
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
        // 전체보기: all_data 파라미터가 있으면 날짜 필터 적용 안 함
        if (isset($filters['all_data']) && ($filters['all_data'] === 'true' || $filters['all_data'] === true || $filters['all_data'] === 1 || $filters['all_data'] === '1')) {
            // 날짜 필터 없이 전체 데이터 반환
            return;
        }

        // 특정 날짜 조회 (최우선순위)
        if (isset($filters['sale_date'])) {
            $query->whereDate('sale_date', $filters['sale_date']);

            return;
        }

        // 날짜 범위 조회 (start_date & end_date)
        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->whereDate('sale_date', '>=', $filters['start_date'])
                  ->whereDate('sale_date', '<=', $filters['end_date']);

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
