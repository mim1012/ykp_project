<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 월마감정산 모델 - 엑셀 "월마감정산" 시트 구현
 *
 * 매월 말 전체 수익/지출을 집계하여 순이익을 계산하는 핵심 모델
 */
class MonthlySettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'year_month',           // 정산 월 (YYYY-MM)
        'dealer_code',          // 대리점 코드
        'settlement_status',    // 정산 상태 (draft/confirmed/closed)

        // 수익 집계 (엑셀 수익 부분)
        'total_sales_amount',       // 총 개통 정산금
        'total_sales_count',        // 총 개통 건수
        'average_margin_rate',      // 평균 마진율
        'total_vat_amount',         // 총 부가세

        // 지출 집계 (엑셀 지출 부분)
        'total_daily_expenses',     // 일일지출 합계
        'total_fixed_expenses',     // 고정지출 합계
        'total_payroll_amount',     // 급여 지출 합계
        'total_refund_amount',      // 환수금액 합계
        'total_expense_amount',     // 총 지출액

        // 최종 계산 (엑셀 순이익 계산)
        'gross_profit',         // 총 수익 (정산금 - 부가세)
        'net_profit',           // 순이익 (총 수익 - 총 지출)
        'profit_rate',          // 순이익률 (%)

        // 전월 대비 분석
        'prev_month_comparison',    // 전월 대비 증감
        'growth_rate',             // 성장률 (%)

        // 관리 정보
        'calculated_at',        // 계산 일시
        'confirmed_at',         // 확정 일시
        'confirmed_by',         // 확정자 (User ID)
        'notes',               // 특이사항 메모
    ];

    protected $casts = [
        'total_sales_amount' => 'decimal:2',
        'total_vat_amount' => 'decimal:2',
        'total_daily_expenses' => 'decimal:2',
        'total_fixed_expenses' => 'decimal:2',
        'total_payroll_amount' => 'decimal:2',
        'total_refund_amount' => 'decimal:2',
        'total_expense_amount' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'net_profit' => 'decimal:2',
        'profit_rate' => 'decimal:2',
        'prev_month_comparison' => 'decimal:2',
        'growth_rate' => 'decimal:2',
        'average_margin_rate' => 'decimal:2',
        'calculated_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    // 관계 정의
    public function dealerProfile(): BelongsTo
    {
        return $this->belongsTo(DealerProfile::class, 'dealer_code', 'dealer_code');
    }

    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    // 상태 체크 메서드들
    public function isDraft(): bool
    {
        return $this->settlement_status === 'draft';
    }

    public function isConfirmed(): bool
    {
        return $this->settlement_status === 'confirmed';
    }

    public function isClosed(): bool
    {
        return $this->settlement_status === 'closed';
    }

    // 수정 가능 여부 체크
    public function isEditable(): bool
    {
        return in_array($this->settlement_status, ['draft', 'confirmed']);
    }

    // 엑셀 공식 구현 메서드들

    /**
     * 총 수익 계산 (정산금 합계)
     */
    public function calculateTotalRevenue(): array
    {
        $salesData = Sale::where('dealer_code', $this->dealer_code)
            ->whereYear('sale_date', substr($this->year_month, 0, 4))
            ->whereMonth('sale_date', substr($this->year_month, 5, 2))
            ->selectRaw('
                COUNT(*) as sales_count,
                SUM(settlement_amount) as total_settlement,
                SUM(tax_amount) as total_vat,
                AVG(margin_rate) as avg_margin_rate
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
     * 총 지출 계산
     */
    public function calculateTotalExpenses(): array
    {
        // 일일지출 합계
        $dailyExpenses = DailyExpense::where('dealer_code', $this->dealer_code)
            ->whereYear('expense_date', substr($this->year_month, 0, 4))
            ->whereMonth('expense_date', substr($this->year_month, 5, 2))
            ->sum('expense_amount');

        // 고정지출 합계
        $fixedExpenses = FixedExpense::where('dealer_code', $this->dealer_code)
            ->where('settlement_month', $this->year_month)
            ->sum('expense_amount');

        // 급여 지출 합계
        $payrollExpenses = Payroll::where('dealer_code', $this->dealer_code)
            ->where('year_month', $this->year_month)
            ->sum('total_salary');

        // 환수금액 합계
        $refundAmount = Refund::where('dealer_code', $this->dealer_code)
            ->whereYear('refund_date', substr($this->year_month, 0, 4))
            ->whereMonth('refund_date', substr($this->year_month, 5, 2))
            ->sum('refund_amount');

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
     * 순이익 및 수익성 계산 (엑셀 핵심 로직)
     */
    public function calculateProfitability(): array
    {
        $grossProfit = $this->total_sales_amount - $this->total_vat_amount;
        $netProfit = $grossProfit - $this->total_expense_amount;
        $profitRate = $this->total_sales_amount > 0 ?
            ($netProfit / $this->total_sales_amount) * 100 : 0;

        return [
            'gross_profit' => $grossProfit,
            'net_profit' => $netProfit,
            'profit_rate' => $profitRate,
        ];
    }

    /**
     * 전월 대비 분석
     */
    public function calculateGrowthAnalysis(): array
    {
        // 전월 데이터 조회
        $prevMonth = date('Y-m', strtotime($this->year_month.'-01 -1 month'));
        $prevSettlement = self::where('dealer_code', $this->dealer_code)
            ->where('year_month', $prevMonth)
            ->first();

        if (! $prevSettlement) {
            return [
                'prev_month_comparison' => 0,
                'growth_rate' => 0,
            ];
        }

        $comparison = $this->net_profit - $prevSettlement->net_profit;
        $growthRate = $prevSettlement->net_profit != 0 ?
            ($comparison / abs($prevSettlement->net_profit)) * 100 : 0;

        return [
            'prev_month_comparison' => $comparison,
            'growth_rate' => $growthRate,
        ];
    }

    // 스코프 쿼리들
    public function scopeForMonth($query, string $yearMonth)
    {
        return $query->where('year_month', $yearMonth);
    }

    public function scopeForDealer($query, string $dealerCode)
    {
        return $query->where('dealer_code', $dealerCode);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('settlement_status', 'confirmed');
    }

    public function scopeDraft($query)
    {
        return $query->where('settlement_status', 'draft');
    }
}
