<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exports\StoreStatisticsExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    /**
     * 매장별 통계 엑셀 다운로드 (본사 전용)
     */
    public function exportStoreStatistics(Request $request)
    {
        // 본사 권한 확인
        if (!auth()->user()->isHeadquarters()) {
            abort(403, '본사 관리자만 접근 가능합니다.');
        }

        // 데이터 조회
        $data = $this->getStoreStatisticsData();

        // 파일명 (URL 인코딩 처리)
        $filename = '매장별_통계_' . date('Y-m-d_His') . '.xlsx';

        // 엑셀 다운로드 (UTF-8 BOM 포함)
        return Excel::download(
            new StoreStatisticsExport($data),
            $filename,
            \Maatwebsite\Excel\Excel::XLSX,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . rawurlencode($filename) . '"',
            ]
        );
    }

    /**
     * 매장별 통계 데이터 조회
     */
    private function getStoreStatisticsData(): array
    {
        // 1. 모든 월 목록 가져오기 (데이터가 있는 월만)
        $months = DB::table('sales')
            ->selectRaw("TO_CHAR(sale_date, 'YYYY-MM') as month")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('month')
            ->toArray();

        // 2. 모든 매장 목록 (지역 포함)
        $stores = DB::table('stores')
            ->join('branches', 'stores.branch_id', '=', 'branches.id')
            ->select(
                'stores.id as store_id',
                'stores.name as store_name',
                'branches.name as branch_name'
            )
            ->orderBy('branches.name')
            ->orderBy('stores.name')
            ->get();

        // 3. 각 매장의 월별 매출/건수 집계
        $storeData = [];

        foreach ($stores as $store) {
            $row = [
                'branch_name' => $store->branch_name,
                'store_name' => $store->store_name,
            ];

            // 전체 총 매출액 및 건수
            $total = DB::table('sales')
                ->where('store_id', $store->store_id)
                ->selectRaw('
                    COALESCE(SUM(settlement_amount), 0) as total_revenue,
                    COUNT(*) as total_count
                ')
                ->first();

            $row['total_revenue'] = $total->total_revenue ?: '';
            $row['total_count'] = $total->total_count ?: '';

            // 월별 데이터
            $monthlyData = DB::table('sales')
                ->where('store_id', $store->store_id)
                ->selectRaw("
                    TO_CHAR(sale_date, 'YYYY-MM') as month,
                    COALESCE(SUM(settlement_amount), 0) as revenue,
                    COUNT(*) as count
                ")
                ->groupBy('month')
                ->get()
                ->keyBy('month');

            // 각 월별 매출/건수 추가
            foreach ($months as $month) {
                if (isset($monthlyData[$month])) {
                    $row["revenue_{$month}"] = $monthlyData[$month]->revenue ?: '';
                    $row["count_{$month}"] = $monthlyData[$month]->count ?: '';
                } else {
                    $row["revenue_{$month}"] = '';
                    $row["count_{$month}"] = '';
                }
            }

            $storeData[] = $row;
        }

        return [
            'months' => $months,
            'stores' => $storeData,
        ];
    }
}
