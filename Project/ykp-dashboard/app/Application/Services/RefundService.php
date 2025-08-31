<?php

namespace App\Application\Services;

use App\Models\Refund;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * 환수 관리 서비스 - 엑셀 "총지출(환수)" 로직 구현
 */
class RefundService
{
    /**
     * 환수 처리 (원래 개통 건과 연결)
     */
    public function processRefund(array $refundData): array
    {
        // 원래 개통 건 조회 (엑셀 VLOOKUP 방식)
        $originalSale = null;
        if (!empty($refundData['activation_id'])) {
            $originalSale = Sale::find($refundData['activation_id']);
        } elseif (!empty($refundData['customer_phone'])) {
            $originalSale = Sale::where('phone_number', $refundData['customer_phone'])
                ->orderBy('sale_date', 'desc')
                ->first();
        }

        // 환수 영향 계산
        $refundImpact = $this->calculateRefundImpact($originalSale, $refundData['refund_amount']);
        
        // 환수 데이터 생성
        $refund = Refund::create([
            ...$refundData,
            'activation_id' => $originalSale?->id,
            'original_amount' => $originalSale?->settlement_amount ?? $refundData['original_amount']
        ]);

        return [
            'refund' => $refund,
            'original_sale' => $originalSale,
            'impact' => $refundImpact
        ];
    }

    /**
     * 환수 영향 계산 (엑셀 수식 구현)
     */
    public function calculateRefundImpact(?Sale $originalSale, float $refundAmount): array
    {
        if (!$originalSale) {
            return [
                'original_margin' => 0,
                'new_margin' => 0,
                'impact_percentage' => 0,
                'total_loss' => $refundAmount
            ];
        }

        $originalMargin = $originalSale->margin_after_tax ?? 0;
        $newMargin = $originalMargin - $refundAmount;
        $impactPercentage = $originalMargin > 0 ? round(($refundAmount / $originalMargin) * 100, 2) : 0;
        
        return [
            'original_margin' => $originalMargin,
            'new_margin' => $newMargin,
            'impact_percentage' => $impactPercentage,
            'total_loss' => $refundAmount
        ];
    }

    /**
     * 환수율 분석 (엑셀 통계 방식)
     */
    public function analyzeRefundRate(string $yearMonth, ?string $dealerCode = null): array
    {
        $startDate = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth();

        // 해당 월 환수 내역
        $refundQuery = Refund::whereBetween('refund_date', [$startDate, $endDate]);
        if ($dealerCode) {
            $refundQuery->where('dealer_code', $dealerCode);
        }
        
        $refunds = $refundQuery->get();
        $totalRefunds = $refunds->count();
        $totalRefundAmount = $refunds->sum('refund_amount');

        // 해당 월 총 개통 건수
        $activationQuery = Sale::whereBetween('sale_date', [$startDate, $endDate]);
        if ($dealerCode) {
            $activationQuery->where('dealer_code', $dealerCode);
        }
        $totalActivations = $activationQuery->count();

        // 환수율 계산
        $refundRate = $totalActivations > 0 ? round(($totalRefunds / $totalActivations) * 100, 2) : 0;

        // 환수 사유별 분석
        $reasonBreakdown = $refunds->groupBy('refund_reason')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total_amount' => $group->sum('refund_amount'),
                'avg_amount' => $group->avg('refund_amount')
            ];
        });

        return [
            'year_month' => $yearMonth,
            'total_refunds' => $totalRefunds,
            'total_activations' => $totalActivations,
            'refund_rate' => $refundRate,
            'total_refund_amount' => $totalRefundAmount,
            'average_refund_amount' => $totalRefunds > 0 ? round($totalRefundAmount / $totalRefunds, 0) : 0,
            'reason_breakdown' => $reasonBreakdown
        ];
    }
}