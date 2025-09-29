<?php

namespace App\Helpers;

use App\Models\DealerProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 판매 데이터 계산 헬퍼
 * 엑셀 개통표와 동일한 계산 로직 + 프로파일 기반 계산
 */
class SalesCalculator
{
    const TAX_RATE = 0.10; // 10%

    /**
     * 판매 행 계산
     *
     * @param  array  $row  입력 데이터
     * @return array 계산된 값들
     */
    public static function computeRow($row)
    {
        // 입력 필드 (서로 다른 키 지원: 프런트/레거시 모두 호환)
        $K = (float) ($row['base_price'] ?? $row['price_setting'] ?? 0);       // 액면가/셋팅가
        $L = (float) ($row['verbal1'] ?? 0);                                     // 구두1
        $M = (float) ($row['verbal2'] ?? 0);                                     // 구두2
        $N = (float) ($row['grade_amount'] ?? 0);                                // 그레이드
        $O = (float) ($row['additional_amount'] ?? $row['addon_amount'] ?? 0);   // 부가추가
        $P = (float) ($row['cash_activation'] ?? $row['paper_cash'] ?? 0);       // 서류상현금개통/현금개통비
        $Q = (float) ($row['usim_fee'] ?? 0);                                    // 유심비(+)
        $R = (float) ($row['new_mnp_discount'] ?? $row['new_mnp_disc'] ?? 0);   // 신규/MNP할인
        $S = (float) ($row['deduction'] ?? 0);                                   // 차감(-)
        $W = (float) ($row['cash_received'] ?? $row['cash_in'] ?? 0);           // 현금받음
        $X = (float) ($row['payback'] ?? 0);                                     // 페이백

        // 계산 필드 (T~Z)
        $T = $K + $L + $M + $N + $O;                   // 리베총계
        $U = $T - $P + $Q + $R - $S;                   // 정산금 (차감은 빼기로 수정)
        $V = round($U * self::TAX_RATE);               // 세금 (10%)
        $Y = $U - $V + $W + $X;                        // 세전마진
        $Z = $Y;                                       // 세후마진 (세전마진과 동일, 세금 이미 차감됨)

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
     * 프로파일 기반 계산 (고도화)
     *
     * @param  array  $row  입력 데이터
     * @param  string|DealerProfile  $profile  프로파일 코드 또는 프로파일 객체
     * @return array 계산된 값들
     */
    public static function calculateWithProfile($row, $profile)
    {
        $startTime = microtime(true);

        try {
            // 프로파일 로드
            if (is_string($profile)) {
                $dealerProfile = Cache::remember(
                    "dealer_profile_{$profile}",
                    300, // 5분 캐시
                    fn () => DealerProfile::active()->byCode($profile)->first()
                );

                if (! $dealerProfile) {
                    throw new \InvalidArgumentException("활성화된 프로파일을 찾을 수 없습니다: {$profile}");
                }
            } else {
                $dealerProfile = $profile;
            }

            // 프로파일 기본값 적용
            $calculationData = $dealerProfile->applyDefaults($row);

            // 프로파일별 세율 적용
            $taxRate = $dealerProfile->tax_rate;

            // 기본 계산 수행
            $result = self::computeRowWithCustomTax($calculationData, $taxRate);

            // 프로파일 정보 추가
            $result['profile'] = [
                'dealer_code' => $dealerProfile->dealer_code,
                'dealer_name' => $dealerProfile->dealer_name,
                'tax_rate' => $dealerProfile->tax_rate,
                'applied_defaults' => $dealerProfile->getCalculationDefaults(),
            ];

            // 성능 메트릭
            $result['performance'] = [
                'calculation_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'profile_cached' => Cache::has("dealer_profile_{$dealerProfile->dealer_code}"),
            ];

            Log::debug('Profile calculation completed', [
                'dealer_code' => $dealerProfile->dealer_code,
                'calculation_time' => $result['performance']['calculation_time_ms'],
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Profile calculation failed', [
                'error' => $e->getMessage(),
                'profile' => is_string($profile) ? $profile : $profile->dealer_code ?? 'unknown',
                'input_data' => $row,
            ]);

            // Fallback to basic calculation
            return self::computeRowWithFallback($row, $e);
        }
    }

    /**
     * 커스텀 세율로 계산
     *
     * @param  array  $row  입력 데이터
     * @param  float  $taxRate  세율
     * @return array 계산된 값들
     */
    protected static function computeRowWithCustomTax($row, $taxRate = null)
    {
        $taxRate = $taxRate ?? self::TAX_RATE;

        // 입력 필드 (K~S, W, X)
        $K = (float) ($row['base_price'] ?? $row['price_setting'] ?? 0);      // 액면가/셋팅가
        $L = (float) ($row['verbal1'] ?? 0);            // 구두1
        $M = (float) ($row['verbal2'] ?? 0);            // 구두2
        $N = (float) ($row['grade_amount'] ?? 0);       // 그레이드
        $O = (float) ($row['additional_amount'] ?? $row['addon_amount'] ?? 0);       // 부가추가
        $P = (float) ($row['cash_activation'] ?? $row['paper_cash'] ?? 0);         // 서류상현금개통
        $Q = (float) ($row['usim_fee'] ?? 0);           // 유심비(+)
        $R = (float) ($row['new_mnp_discount'] ?? $row['new_mnp_disc'] ?? 0);       // 신규/MNP할인
        $S = (float) ($row['deduction'] ?? 0);          // 차감(-)
        $W = (float) ($row['cash_received'] ?? $row['cash_in'] ?? 0);            // 현금받음
        $X = (float) ($row['payback'] ?? 0);            // 페이백

        // 계산 필드 (T~Z)
        $T = $K + $L + $M + $N + $O;                   // 리베총계
        $U = $T - $P + $Q + $R - $S;                   // 정산금 (차감은 빼기로 수정)
        $V = round($U * $taxRate);                     // 세금 (커스텀 세율)
        $Y = $U - $V + $W + $X;                        // 세전마진
        $Z = $Y;                                       // 세후마진 (세전마진과 동일, 세금 이미 차감됨)

        return [
            'total_rebate' => $T,       // 리베총계
            'settlement' => $U,         // 정산금
            'tax' => $V,               // 세금
            'margin_before' => $Y,      // 세전마진
            'margin_after' => $Z,       // 세후마진
            'tax_rate_used' => $taxRate, // 적용된 세율
        ];
    }

    /**
     * Fallback 계산 (프로파일 실패시)
     *
     * @param  array  $row  입력 데이터
     * @param  \Exception  $originalError  원본 오류
     * @return array 계산된 값들
     */
    protected static function computeRowWithFallback($row, $originalError)
    {
        $result = self::computeRow($row);
        $result['fallback'] = [
            'used' => true,
            'reason' => $originalError->getMessage(),
            'profile_error' => true,
        ];

        return $result;
    }

    /**
     * 배치 프로파일 계산
     *
     * @param  array  $rows  입력 데이터 배열
     * @param  string|DealerProfile  $profile  프로파일
     * @param  array  $options  옵션
     * @return array 계산 결과
     */
    public static function calculateBatchWithProfile($rows, $profile, $options = [])
    {
        $startTime = microtime(true);
        $results = [];
        $successCount = 0;
        $errorCount = 0;
        $fallbackCount = 0;

        // 프로파일 미리 로드 (배치 성능 최적화)
        if (is_string($profile)) {
            $dealerProfile = DealerProfile::active()->byCode($profile)->first();
            if (! $dealerProfile) {
                throw new \InvalidArgumentException("프로파일을 찾을 수 없습니다: {$profile}");
            }
        } else {
            $dealerProfile = $profile;
        }

        foreach ($rows as $index => $row) {
            try {
                $calculation = self::calculateWithProfile($row, $dealerProfile);

                $results[$index] = [
                    'status' => 'success',
                    'input' => $row,
                    'result' => $calculation,
                ];

                $successCount++;

                if (isset($calculation['fallback'])) {
                    $fallbackCount++;
                }

            } catch (\Exception $e) {
                $results[$index] = [
                    'status' => 'error',
                    'input' => $row,
                    'message' => $e->getMessage(),
                ];
                $errorCount++;
            }
        }

        $totalTime = round((microtime(true) - $startTime) * 1000, 2);
        $avgTime = count($rows) > 0 ? round($totalTime / count($rows), 2) : 0;

        return [
            'results' => $results,
            'summary' => [
                'total' => count($rows),
                'success' => $successCount,
                'errors' => $errorCount,
                'fallbacks' => $fallbackCount,
            ],
            'performance' => [
                'total_time_ms' => $totalTime,
                'avg_time_per_row_ms' => $avgTime,
                'rows_per_second' => $avgTime > 0 ? round(1000 / $avgTime, 1) : 0,
            ],
            'profile_info' => [
                'dealer_code' => $dealerProfile->dealer_code,
                'dealer_name' => $dealerProfile->dealer_name,
            ],
        ];
    }

    /**
     * 데이터 검증 (프로파일 지원)
     *
     * @param  array  $row  입력 데이터
     * @param  DealerProfile|null  $profile  프로파일
     * @return array 검증 결과 [valid: bool, errors: array]
     */
    public static function validateRowWithProfile($row, $profile = null)
    {
        $errors = [];

        // 기본 검증
        $baseValidation = self::validateRow($row);
        $errors = array_merge($errors, $baseValidation['errors']);

        // 프로파일별 추가 검증
        if ($profile && $profile->custom_calculation_rules) {
            foreach ($profile->custom_calculation_rules as $rule) {
                if (isset($rule['validation'])) {
                    $validationRule = $rule['validation'];

                    // 커스텀 검증 로직 실행
                    if (isset($validationRule['required_fields'])) {
                        foreach ($validationRule['required_fields'] as $field) {
                            if (empty($row[$field])) {
                                $errors[] = "{$field}는 이 프로파일에서 필수 필드입니다";
                            }
                        }
                    }
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * 기존 데이터 검증 (호환성 유지)
     *
     * @param  array  $row  입력 데이터
     * @return array 검증 결과 [valid: bool, errors: array]
     */
    public static function validateRow($row)
    {
        $errors = [];

        // 필수 필드 체크
        $requiredFields = ['seller', 'opened_on'];
        foreach ($requiredFields as $field) {
            if (empty($row[$field])) {
                if ($field === 'seller') {
                    $errors[] = '판매자가 누락되었습니다';
                } elseif ($field === 'opened_on') {
                    $errors[] = '개통일이 누락되었습니다';
                }
            }
        }

        // 숫자 필드 체크
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
