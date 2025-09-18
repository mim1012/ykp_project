<?php

namespace App\Application\Services;

use App\Http\Requests\CreateSaleRequest;
use App\Models\User;

interface SaleServiceInterface
{
    /**
     * 대량 판매 데이터 저장
     *
     * @return array{success: bool, message: string, saved_count: int}
     */
    public function bulkCreate(CreateSaleRequest $request, User $user): array;

    /**
     * 판매 데이터 조회 (권한별 필터링 포함)
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getFilteredSales(array $filters, User $user);

    /**
     * 통계 데이터 조회
     */
    public function getStatistics(string $startDate, string $endDate, User $user): array;

    /**
     * 대량 삭제
     *
     * @param  array  $saleIds  삭제할 판매 ID 목록
     * @param  User  $user  현재 사용자
     * @return array{success: bool, message: string, deleted_count: int}
     */
    public function bulkDelete(array $saleIds, User $user): array;
}
