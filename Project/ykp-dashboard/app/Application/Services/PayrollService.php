<?php

namespace App\Application\Services;

use App\Models\Payroll;
use App\Models\Sale;
use App\Models\DealerProfile;
use Illuminate\Support\Facades\DB;

/**
 * 급여 관리 서비스 - 엑셀 "점장급여(수기입력)" 로직 구현
 */
class PayrollService
{
    /**
     * 엑셀 "직원인센합계" 로직 - 개통 실적 기반 인센티브 자동 계산
     */
    public function calculateIncentive(string $yearMonth, string $dealerCode, string $employeeId, ?string $position = null): float
    {
        // 해당 월의 개통 실적 조회
        $activationCount = Sale::where('dealer_code', $dealerCode)
            ->whereYear('sale_date', substr($yearMonth, 0, 4))
            ->whereMonth('sale_date', substr($yearMonth, 5, 2))
            ->count();

        // 대리점별 인센티브 정책 조회
        $dealerProfile = DealerProfile::where('dealer_code', $dealerCode)->first();
        $baseIncentiveRate = $dealerProfile?->default_payback_rate ?? 5000;
        
        // 직급별 인센티브 비율 (엑셀 정책 반영)
        $positionMultipliers = [
            '점장' => 1.0,
            '부점장' => 0.8,
            '직원' => 0.5,
            '상담원' => 0.3,
            '인턴' => 0.2
        ];
        
        $multiplier = $positionMultipliers[$position] ?? 0.5;
        
        return round($activationCount * $baseIncentiveRate * $multiplier, 0);
    }

    /**
     * 월별 급여 처리 (엑셀 월마감정산 연동)
     */
    public function processMonthlyPayroll(string $yearMonth, ?string $dealerCode = null): array
    {
        $query = Payroll::where('year_month', $yearMonth);
        
        if ($dealerCode) {
            $query->where('dealer_code', $dealerCode);
        }

        $payrolls = $query->get();
        
        // 각 직원의 인센티브 재계산
        foreach ($payrolls as $payroll) {
            $newIncentive = $this->calculateIncentive(
                $payroll->year_month,
                $payroll->dealer_code,
                $payroll->employee_id,
                $payroll->position
            );
            
            $newTotal = $payroll->base_salary + $newIncentive + $payroll->bonus_amount - $payroll->deduction_amount;
            
            $payroll->update([
                'incentive_amount' => $newIncentive,
                'total_salary' => $newTotal
            ]);
        }

        return [
            'total_payroll_amount' => $payrolls->sum('total_salary'),
            'paid_amount' => $payrolls->where('payment_status', 'paid')->sum('total_salary'),
            'pending_amount' => $payrolls->where('payment_status', 'pending')->sum('total_salary'),
            'employee_count' => $payrolls->count()
        ];
    }
}