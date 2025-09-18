<?php

namespace App\Application\Services;

use App\Models\DailyExpense;
use App\Models\DealerProfile;
use App\Models\FixedExpense;
use App\Models\MonthlySettlement;
use App\Models\Payroll;
use App\Models\Refund;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 월마감정산 서비스 - 엑셀 "월마감정산" 로직 완전 구현
 */
class MonthlySettlementService
{
    /**
     * 월마감정산 자동 생성/업데이트 (엑셀의 전체 로직)
     */
    public function generateMonthlySettlement(string $yearMonth, string $dealerCode): MonthlySettlement
    {
        return DB::transaction(function () use ($yearMonth, $dealerCode) {
            // 기존 정산 조회 또는 새로 생성
            $settlement = MonthlySettlement::firstOrNew([
                'year_month' => $yearMonth,
                'dealer_code' => $dealerCode,
            ]);

            // 1. 수익 집계 계산
            $revenueData = $this->calculateRevenue($yearMonth, $dealerCode);

            // 2. 지출 집계 계산
            $expenseData = $this->calculateExpenses($yearMonth, $dealerCode);

            // 3. 최종 손익 계산
            $profitData = $this->calculateProfitability(
                $revenueData['total_sales_amount'],
                $revenueData['total_vat_amount'],
                $expenseData['total_expense_amount']
            );

            // 4. 전월 대비 분석
            $growthData = $this->calculateGrowthAnalysis($yearMonth, $dealerCode, $profitData['net_profit']);

            // 모든 데이터 업데이트
            $settlement->fill(array_merge($revenueData, $expenseData, $profitData, $growthData));
            $settlement->calculated_at = now();
            $settlement->save();

            return $settlement;
        });
    }

    /**
     * 수익 집계 계산 (엑셀 수익 부분)
     */
    private function calculateRevenue(string $yearMonth, string $dealerCode): array
    {
        $year = substr($yearMonth, 0, 4);
        $month = substr($yearMonth, 5, 2);

        $salesData = Sale::where('dealer_code', $dealerCode)
            ->whereYear('sale_date', $year)
            ->whereMonth('sale_date', $month)
            ->selectRaw('
                COUNT(*) as sales_count,
                COALESCE(SUM(settlement_amount), 0) as total_settlement,
                COALESCE(SUM(tax_amount), 0) as total_vat,
                COALESCE(AVG(margin_rate), 0) as avg_margin_rate
            ')
            ->first();

        return [
            'total_sales_amount' => $salesData->total_settlement ?? 0,
            'total_sales_count' => $salesData->sales_count ?? 0,
            'total_vat_amount' => $salesData->total_vat ?? 0,
            'average_margin_rate' => $salesData->avg_margin_rate ?? 0,
        ];
    }

    /**
     * 지출 집계 계산 (엑셀 지출 부분)
     */
    private function calculateExpenses(string $yearMonth, string $dealerCode): array
    {
        $year = substr($yearMonth, 0, 4);
        $month = substr($yearMonth, 5, 2);

        // 일일지출 합계
        $dailyExpenses = DailyExpense::where('dealer_code', $dealerCode)
            ->whereYear('expense_date', $year)
            ->whereMonth('expense_date', $month)
            ->sum('expense_amount') ?? 0;

        // 고정지출 합계
        $fixedExpenses = FixedExpense::where('dealer_code', $dealerCode)
            ->where('settlement_month', $yearMonth)
            ->sum('expense_amount') ?? 0;

        // 급여 지출 합계
        $payrollExpenses = Payroll::where('dealer_code', $dealerCode)
            ->where('year_month', $yearMonth)
            ->sum('total_salary') ?? 0;

        // 환수금액 합계
        $refundAmount = Refund::where('dealer_code', $dealerCode)
            ->whereYear('refund_date', $year)
            ->whereMonth('refund_date', $month)
            ->sum('refund_amount') ?? 0;

        $totalExpenses = $dailyExpenses + $fixedExpenses + $payrollExpenses + $refundAmount;

        return [
            'total_daily_expenses' => $dailyExpenses,
            'total_fixed_expenses' => $fixedExpenses,
            'total_payroll_amount' => $payrollExpenses,
            'total_refund_amount' => $refundAmount,
            'total_expense_amount' => $totalExpenses,
        ];
    }

    /**
     * 손익 계산 (엑셀 핵심 공식)
     */
    private function calculateProfitability(float $totalSales, float $totalVat, float $totalExpenses): array
    {
        // 총수익 = 정산금 - 부가세
        $grossProfit = $totalSales - $totalVat;

        // 순이익 = 총수익 - 총지출
        $netProfit = $grossProfit - $totalExpenses;

        // 순이익률 = (순이익 / 총정산금) × 100
        $profitRate = $totalSales > 0 ? ($netProfit / $totalSales) * 100 : 0;

        return [
            'gross_profit' => $grossProfit,
            'net_profit' => $netProfit,
            'profit_rate' => $profitRate,
        ];
    }

    /**
     * 전월 대비 성장률 분석
     */
    private function calculateGrowthAnalysis(string $yearMonth, string $dealerCode, float $currentNetProfit): array
    {
        // 전월 데이터 조회
        $prevMonth = Carbon::parse($yearMonth.'-01')->subMonth()->format('Y-m');
        $prevSettlement = MonthlySettlement::where('dealer_code', $dealerCode)
            ->where('year_month', $prevMonth)
            ->first();

        if (! $prevSettlement) {
            return [
                'prev_month_comparison' => 0,
                'growth_rate' => 0,
            ];
        }

        $comparison = $currentNetProfit - $prevSettlement->net_profit;
        $growthRate = $prevSettlement->net_profit != 0 ?
            ($comparison / abs($prevSettlement->net_profit)) * 100 : 0;

        return [
            'prev_month_comparison' => $comparison,
            'growth_rate' => $growthRate,
        ];
    }

    /**
     * 전체 대리점 월마감정산 일괄 생성 (월말 배치 작업)
     */
    public function generateAllDealerSettlements(string $yearMonth): array
    {
        $results = [];
        $dealers = DealerProfile::where('status', 'active')->get();

        foreach ($dealers as $dealer) {
            try {
                $settlement = $this->generateMonthlySettlement($yearMonth, $dealer->dealer_code);
                $results[] = [
                    'dealer_code' => $dealer->dealer_code,
                    'dealer_name' => $dealer->dealer_name,
                    'status' => 'success',
                    'net_profit' => $settlement->net_profit,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'dealer_code' => $dealer->dealer_code,
                    'dealer_name' => $dealer->dealer_name,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * 월마감정산 확정 처리
     */
    public function confirmSettlement(int $settlementId, int $confirmedBy): MonthlySettlement
    {
        $settlement = MonthlySettlement::findOrFail($settlementId);

        if ($settlement->settlement_status !== 'draft') {
            throw new \Exception('임시저장 상태의 정산만 확정할 수 있습니다.');
        }

        $settlement->update([
            'settlement_status' => 'confirmed',
            'confirmed_at' => now(),
            'confirmed_by' => $confirmedBy,
        ]);

        return $settlement;
    }

    /**
     * 월마감정산 마감 처리 (수정 불가)
     */
    public function closeSettlement(int $settlementId): MonthlySettlement
    {
        $settlement = MonthlySettlement::findOrFail($settlementId);

        if ($settlement->settlement_status !== 'confirmed') {
            throw new \Exception('확정된 정산만 마감할 수 있습니다.');
        }

        $settlement->update([
            'settlement_status' => 'closed',
        ]);

        return $settlement;
    }

    /**
     * 월별 통합 대시보드 데이터 (전체 대리점 합계)
     */
    public function getMonthlyDashboardData(string $yearMonth): array
    {
        $settlements = MonthlySettlement::where('year_month', $yearMonth)->get();

        $totalRevenue = $settlements->sum('total_sales_amount');
        $totalExpenses = $settlements->sum('total_expense_amount');
        $totalNetProfit = $settlements->sum('net_profit');
        $totalSalesCount = $settlements->sum('total_sales_count');

        // 대리점별 수익성 순위
        $dealerRanking = $settlements->sortByDesc('net_profit')
            ->take(10)
            ->map(function ($settlement) {
                return [
                    'dealer_code' => $settlement->dealer_code,
                    'dealer_name' => $settlement->dealerProfile?->dealer_name ?? $settlement->dealer_code,
                    'net_profit' => $settlement->net_profit,
                    'profit_rate' => $settlement->profit_rate,
                    'sales_count' => $settlement->total_sales_count,
                ];
            });

        return [
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_expenses' => $totalExpenses,
                'total_net_profit' => $totalNetProfit,
                'total_sales_count' => $totalSalesCount,
                'average_profit_rate' => $totalRevenue > 0 ? ($totalNetProfit / $totalRevenue) * 100 : 0,
                'dealer_count' => $settlements->count(),
            ],
            'dealer_ranking' => $dealerRanking,
            'expense_breakdown' => [
                'daily_expenses' => $settlements->sum('total_daily_expenses'),
                'fixed_expenses' => $settlements->sum('total_fixed_expenses'),
                'payroll_expenses' => $settlements->sum('total_payroll_amount'),
                'refund_amount' => $settlements->sum('total_refund_amount'),
            ],
        ];
    }

    /**
     * 연간 트렌드 분석
     */
    public function getYearlyTrend(int $year, ?string $dealerCode = null): array
    {
        $query = MonthlySettlement::whereYear('year_month', $year);

        if ($dealerCode) {
            $query->where('dealer_code', $dealerCode);
        }

        $settlements = $query->selectRaw('
            year_month,
            SUM(total_sales_amount) as monthly_revenue,
            SUM(total_expense_amount) as monthly_expenses,
            SUM(net_profit) as monthly_profit,
            COUNT(*) as dealer_count
        ')
            ->groupBy('year_month')
            ->orderBy('year_month')
            ->get();

        return $settlements->map(function ($settlement) {
            return [
                'month' => $settlement->year_month,
                'revenue' => $settlement->monthly_revenue,
                'expenses' => $settlement->monthly_expenses,
                'profit' => $settlement->monthly_profit,
                'dealer_count' => $settlement->dealer_count,
            ];
        })->toArray();
    }
}
