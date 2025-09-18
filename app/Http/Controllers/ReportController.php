<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    /**
     * 보고서 요약 데이터 조회
     */
    public function summary(Request $request)
    {
        $from = Carbon::parse($request->from ?? now()->startOfMonth());
        $to = Carbon::parse($request->to ?? now()->endOfMonth());
        $group = $request->group ?? 'day'; // day, week, month

        // RBAC 필터 적용
        $query = $this->applyRBACFilter(Sale::query(), $request);

        // KPI 계산
        $kpis = $this->calculateKPIs($query->clone(), $from, $to);

        // 시계열 데이터
        $series = $this->getTimeSeries($query->clone(), $from, $to, $group);

        // 대리점별 집계
        $byDealer = $this->getByDealer($query->clone(), $from, $to);

        // 지사별 집계 (본사만)
        $byBranch = null;
        if ($request->user_role === 'headquarters') {
            $byBranch = $this->getByBranch($from, $to);
        }

        // 매장별 집계 (본사/지사만)
        $byStore = null;
        if (in_array($request->user_role, ['headquarters', 'branch'])) {
            $byStore = $this->getByStore($query->clone(), $from, $to);
        }

        return response()->json([
            'kpis' => $kpis,
            'series' => $series,
            'byDealer' => $byDealer,
            'byBranch' => $byBranch,
            'byStore' => $byStore,
            'period' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'group' => $group,
            ],
        ]);
    }

    /**
     * RBAC 필터 적용
     */
    private function applyRBACFilter($query, Request $request)
    {
        $query->whereBetween('sale_date', [$request->from ?? now()->startOfMonth(), $request->to ?? now()->endOfMonth()]);

        if ($request->user_role === 'branch' || $request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id ?? $request->user_branch_id);
        }

        if ($request->user_role === 'store' || $request->has('store_id')) {
            $query->where('store_id', $request->store_id ?? $request->user_store_id);
        }

        return $query;
    }

    /**
     * KPI 계산
     */
    private function calculateKPIs($query, $from, $to)
    {
        $current = $query->select(
            DB::raw('COALESCE(SUM(settlement_amount), 0) as totalSales'),
            DB::raw('COUNT(*) as totalOrders'),
            DB::raw('COALESCE(AVG(settlement_amount), 0) as avgTicket')
        )->first();

        // 이전 기간 계산 (동일 기간)
        $days = $from->diffInDays($to) + 1;
        $prevFrom = $from->copy()->subDays($days);
        $prevTo = $from->copy()->subDay();

        $previous = Sale::whereBetween('sale_date', [$prevFrom, $prevTo])
            ->select(DB::raw('COALESCE(SUM(settlement_amount), 0) as totalSales'))
            ->first();

        $vsPrevPeriod = $previous->totalSales > 0
            ? round((($current->totalSales - $previous->totalSales) / $previous->totalSales) * 100, 1)
            : 0;

        return [
            'totalSales' => (int) $current->totalSales,
            'totalOrders' => (int) $current->totalOrders,
            'avgTicket' => (int) $current->avgTicket,
            'vsPrevPeriodPercent' => $vsPrevPeriod,
        ];
    }

    /**
     * 시계열 데이터
     */
    private function getTimeSeries($query, $from, $to, $group)
    {
        // DB 타입에 따른 날짜 포맷 함수 선택
        if (config('database.default') === 'pgsql') {
            $dateFormat = match ($group) {
                'month' => 'YYYY-MM',
                'week' => 'YYYY-"W"IW',
                default => 'YYYY-MM-DD'
            };
            $dateFunction = "TO_CHAR(sale_date, '{$dateFormat}')";
        } else {
            $dateFormat = match ($group) {
                'month' => '%Y-%m',
                'week' => '%Y-W%V',
                default => '%Y-%m-%d'
            };
            $dateFunction = "strftime('{$dateFormat}', sale_date)";
        }

        $results = $query->select(
            DB::raw("{$dateFunction} as label"),
            DB::raw('SUM(settlement_amount) as value'),
            DB::raw('COUNT(*) as count')
        )
            ->groupBy('label')
            ->orderBy('label')
            ->get();

        return $results->map(function ($item) use ($group) {
            $label = match ($group) {
                'month' => Carbon::createFromFormat('Y-m', $item->label)->format('Y년 n월'),
                'week' => str_replace('-W', '년 ', $item->label).'주',
                default => Carbon::parse($item->label)->format('n월 j일')
            };

            return [
                'label' => $label,
                'value' => (int) $item->value,
                'count' => (int) $item->count,
            ];
        });
    }

    /**
     * 대리점별 집계
     */
    private function getByDealer($query, $from, $to)
    {
        return $query->select(
            'salesperson as dealer',
            DB::raw('SUM(settlement_amount) as sales'),
            DB::raw('COUNT(*) as orders'),
            DB::raw('AVG(settlement_amount) as avgTicket')
        )
            ->whereNotNull('salesperson')
            ->where('salesperson', '!=', '')
            ->groupBy('salesperson')
            ->orderByDesc('sales')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'dealer' => $item->dealer,
                    'sales' => (int) $item->sales,
                    'orders' => (int) $item->orders,
                    'avgTicket' => (int) $item->avgTicket,
                ];
            });
    }

    /**
     * 지사별 집계
     */
    private function getByBranch($from, $to)
    {
        return Branch::withSum(['sales' => function ($query) use ($from, $to) {
            $query->whereBetween('sale_date', [$from, $to]);
        }], 'settlement_amount')
            ->withCount(['sales' => function ($query) use ($from, $to) {
                $query->whereBetween('sale_date', [$from, $to]);
            }])
            ->get()
            ->map(function ($branch) {
                return [
                    'branch' => $branch->name,
                    'sales' => (int) ($branch->sales_sum_settlement_amount ?? 0),
                    'orders' => (int) ($branch->sales_count ?? 0),
                    'avgTicket' => $branch->sales_count > 0
                        ? (int) ($branch->sales_sum_settlement_amount / $branch->sales_count)
                        : 0,
                ];
            })
            ->sortByDesc('sales')
            ->values();
    }

    /**
     * 매장별 집계
     */
    private function getByStore($query, $from, $to)
    {
        $storeIds = $query->pluck('store_id')->unique();

        return Store::whereIn('id', $storeIds)
            ->withSum(['sales' => function ($query) use ($from, $to) {
                $query->whereBetween('sale_date', [$from, $to]);
            }], 'settlement_amount')
            ->withCount(['sales' => function ($query) use ($from, $to) {
                $query->whereBetween('sale_date', [$from, $to]);
            }])
            ->get()
            ->map(function ($store) {
                return [
                    'store' => $store->name,
                    'sales' => (int) ($store->sales_sum_settlement_amount ?? 0),
                    'orders' => (int) ($store->sales_count ?? 0),
                    'avgTicket' => $store->sales_count > 0
                        ? (int) ($store->sales_sum_settlement_amount / $store->sales_count)
                        : 0,
                ];
            })
            ->sortByDesc('sales')
            ->values();
    }

    /**
     * Excel 내보내기
     */
    public function exportExcel(Request $request)
    {
        $data = $this->summary($request);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        // 헤더 설정
        $sheet->setCellValue('A1', 'YKP ERP 보고서');
        $sheet->setCellValue('A2', '기간: '.$request->from.' ~ '.$request->to);

        // KPI
        $sheet->setCellValue('A4', '주요 지표');
        $sheet->setCellValue('A5', '총매출');
        $sheet->setCellValue('B5', '₩'.number_format($data->original['kpis']['totalSales']));
        $sheet->setCellValue('A6', '판매건수');
        $sheet->setCellValue('B6', number_format($data->original['kpis']['totalOrders']));
        $sheet->setCellValue('A7', '평균구매액');
        $sheet->setCellValue('B7', '₩'.number_format($data->original['kpis']['avgTicket']));

        // 시계열 데이터
        $row = 10;
        $sheet->setCellValue('A9', '일별 매출');
        $sheet->setCellValue('A'.$row, '날짜');
        $sheet->setCellValue('B'.$row, '매출');
        $sheet->setCellValue('C'.$row, '건수');

        foreach ($data->original['series'] as $item) {
            $row++;
            $sheet->setCellValue('A'.$row, $item['label']);
            $sheet->setCellValue('B'.$row, '₩'.number_format($item['value']));
            $sheet->setCellValue('C'.$row, number_format($item['count']));
        }

        // 파일 다운로드
        $writer = new Xlsx($spreadsheet);
        $fileName = 'YKP_보고서_'.date('Ymd_His').'.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$fileName.'"');
        $writer->save('php://output');
    }

    /**
     * PDF 내보내기
     */
    public function exportPDF(Request $request)
    {
        $data = $this->summary($request);

        // PDF 생성 로직 (TCPDF 또는 DomPDF 사용)
        // 구현 생략 - 실제로는 PDF 라이브러리 사용

        return response()->json(['message' => 'PDF export will be implemented']);
    }
}
