<?php

namespace App\Application\Services;

use App\Models\DailyExpense;
use App\Models\FixedExpense;
use Carbon\Carbon;

/**
 * 지출 관리 서비스 - 엑셀 "총지출" 로직 구현
 */
class ExpenseService
{
    /**
     * 월별 총지출 계산 (엑셀 월마감정산 연동)
     */
    public function calculateMonthlyExpenses(string $yearMonth, ?string $dealerCode = null): array
    {
        $startDate = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth();

        // 일일지출 집계
        $dailyExpenseQuery = DailyExpense::whereBetween('expense_date', [$startDate, $endDate]);
        if ($dealerCode) {
            $dailyExpenseQuery->where('dealer_code', $dealerCode);
        }
        $dailyExpenses = $dailyExpenseQuery->sum('amount');

        // 고정지출 집계
        $fixedExpenseQuery = FixedExpense::where('year_month', $yearMonth);
        if ($dealerCode) {
            $fixedExpenseQuery->where('dealer_code', $dealerCode);
        }
        $fixedExpenses = $fixedExpenseQuery->sum('amount');

        return [
            'year_month' => $yearMonth,
            'total_daily_expenses' => $dailyExpenses,
            'total_fixed_expenses' => $fixedExpenses,
            'total_expenses' => $dailyExpenses + $fixedExpenses,
        ];
    }

    /**
     * 현금 흐름 예측
     */
    public function cashFlowProjection(int $days = 30): array
    {
        $upcomingPayments = FixedExpense::where('payment_status', 'pending')
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays($days)->toDateString()])
            ->orderBy('due_date')
            ->get();

        return [
            'total_upcoming' => $upcomingPayments->sum('amount'),
            'payment_count' => $upcomingPayments->count(),
            'upcoming_payments' => $upcomingPayments,
        ];
    }
}
