<?php

namespace App\Helpers;

/**
 * 판매 데이터 계산 헬퍼
 * 엑셀 개통표와 동일한 계산 로직
 */
class SalesCalculator
{
    const TAX_RATE = 0.133; // 13.3%

    /**
     * 판매 행 계산
     *
     * @param  array  $row  입력 데이터
     * @return array 계산된 값들
     */
    public static function computeRow($row)
    {
        // 입력 필드 (K~S, W, X)
        $K = (float) ($row['price_setting'] ?? 0);      // 액면가/셋팅가
        $L = (float) ($row['verbal1'] ?? 0);            // 구두1
        $M = (float) ($row['verbal2'] ?? 0);            // 구두2
        $N = (float) ($row['grade_amount'] ?? 0);       // 그레이드
        $O = (float) ($row['addon_amount'] ?? 0);       // 부가추가
        $P = (float) ($row['paper_cash'] ?? 0);         // 서류상현금개통
        $Q = (float) ($row['usim_fee'] ?? 0);           // 유심비(+)
        $R = (float) ($row['new_mnp_disc'] ?? 0);       // 신규/MNP할인
        $S = (float) ($row['deduction'] ?? 0);          // 차감(-)
        $W = (float) ($row['cash_in'] ?? 0);            // 현금받음
        $X = (float) ($row['payback'] ?? 0);            // 페이백

        // 계산 필드 (T~Z)
        $T = $K + $L + $M + $N + $O;                   // 리베총계
        $U = $T - $P + $Q + $R + $S;                   // 정산금
        $V = round($U * self::TAX_RATE);               // 세금 (13.3%)
        $Y = $U - $V + $W + $X;                        // 세전마진
        $Z = $V + $Y;                                  // 세후마진

        return [
            'total_rebate' => $T,       // 리베총계
            'settlement' => $U,         // 정산금
            'tax' => $V,               // 세금
            'margin_before' => $Y,      // 세전마진
            'margin_after' => $Z,        // 세후마진
        ];
    }

    /**
     * 월간 요약 계산
     *
     * @param  array  $sales  판매 데이터 배열
     * @return array 요약 통계
     */
    public static function computeMonthlySummary($sales)
    {
        $totalUnits = count($sales);
        $totalSettlement = 0;
        $totalTax = 0;
        $totalMarginBefore = 0;
        $totalMarginAfter = 0;

        foreach ($sales as $sale) {
            $calc = self::computeRow($sale);
            $totalSettlement += $calc['settlement'];
            $totalTax += $calc['tax'];
            $totalMarginBefore += $calc['margin_before'];
            $totalMarginAfter += $calc['margin_after'];
        }

        return [
            'total_units' => $totalUnits,
            'avg_margin' => $totalUnits > 0 ? round($totalMarginAfter / $totalUnits) : 0,
            'total_settlement' => $totalSettlement,
            'total_tax' => $totalTax,
            'total_margin_before' => $totalMarginBefore,
            'total_margin_after' => $totalMarginAfter,
        ];
    }

    /**
     * 데이터 검증
     *
     * @param  array  $row  입력 데이터
     * @return array 검증 결과 [valid: bool, errors: array]
     */
    public static function validateRow($row)
    {
        $errors = [];

        // 필수 필드 체크
        if (empty($row['seller'])) {
            $errors[] = '판매자가 누락되었습니다';
        }

        if (empty($row['opened_on'])) {
            $errors[] = '개통일이 누락되었습니다';
        }

        // 숫자 필드 검증
        $numericFields = [
            'price_setting', 'verbal1', 'verbal2', 'grade_amount', 'addon_amount',
            'paper_cash', 'usim_fee', 'new_mnp_disc', 'deduction', 'cash_in', 'payback',
        ];

        foreach ($numericFields as $field) {
            if (isset($row[$field]) && ! is_numeric($row[$field])) {
                $errors[] = "{$field} 필드는 숫자여야 합니다";
            }
        }

        // 음수 체크 (특정 필드만 음수 허용)
        $allowNegative = ['new_mnp_disc', 'deduction', 'payback'];
        foreach ($numericFields as $field) {
            if (! in_array($field, $allowNegative) && isset($row[$field]) && $row[$field] < 0) {
                $errors[] = "{$field} 필드는 음수가 될 수 없습니다";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
