<?php

namespace Tests\Unit;

use App\Helpers\SalesCalculator;
use PHPUnit\Framework\TestCase;

class DeductionCalculationTest extends TestCase
{
    /**
     * 부/소세(차감) 계산이 올바르게 빼기로 처리되는지 테스트
     */
    public function test_deduction_is_subtracted_not_added()
    {
        // 테스트 데이터 준비
        $testData = [
            'base_price' => 100000,      // K: 액면가
            'verbal1' => 10000,          // L: 구두1
            'verbal2' => 5000,           // M: 구두2
            'grade_amount' => 3000,      // N: 그레이드
            'additional_amount' => 2000,  // O: 부가추가
            'cash_activation' => 0,      // P: 서류상현금개통
            'usim_fee' => 0,            // Q: 유심비
            'new_mnp_discount' => 0,    // R: 신규/MNP할인
            'deduction' => 15000,        // S: 차감 (부/소세)
            'cash_received' => 0,        // W: 현금받음
            'payback' => 0              // X: 페이백
        ];

        // 계산 실행
        $result = SalesCalculator::computeRow($testData);

        // 기대값 계산
        $expectedTotalRebate = 100000 + 10000 + 5000 + 3000 + 2000; // = 120000
        $expectedSettlement = $expectedTotalRebate - 15000; // = 105000 (차감이 빼기로 적용)
        $expectedTax = round($expectedSettlement * 0.10); // = 10500
        $expectedMarginBefore = $expectedSettlement - $expectedTax; // = 94500
        $expectedMarginAfter = $expectedMarginBefore - $expectedTax; // = 84000

        // 검증
        $this->assertEquals($expectedTotalRebate, $result['total_rebate'], '리베총계 계산 오류');
        $this->assertEquals($expectedSettlement, $result['settlement'], '정산금 계산 오류: 차감이 빼기로 처리되어야 함');
        $this->assertEquals($expectedTax, $result['tax'], '세금 계산 오류');
        $this->assertEquals($expectedMarginBefore, $result['margin_before'], '세전마진 계산 오류');
        $this->assertEquals($expectedMarginAfter, $result['margin_after'], '세후마진 계산 오류');

        // 잘못된 계산과 비교 (이전 버그)
        $wrongSettlement = $expectedTotalRebate + 15000; // = 135000 (잘못된 계산)
        $this->assertNotEquals($wrongSettlement, $result['settlement'], '차감이 더하기로 처리되면 안됨');
    }

    /**
     * 여러 케이스에 대한 차감 계산 테스트
     */
    public function test_various_deduction_cases()
    {
        $testCases = [
            [
                'name' => '차감 0원',
                'data' => [
                    'base_price' => 50000,
                    'deduction' => 0,
                ],
                'expected_settlement' => 50000, // 차감 없음
            ],
            [
                'name' => '차감 10000원',
                'data' => [
                    'base_price' => 50000,
                    'deduction' => 10000,
                ],
                'expected_settlement' => 40000, // 50000 - 10000
            ],
            [
                'name' => '차감이 리베총계보다 큰 경우',
                'data' => [
                    'base_price' => 30000,
                    'deduction' => 50000,
                ],
                'expected_settlement' => -20000, // 30000 - 50000 = -20000 (음수 가능)
            ],
        ];

        foreach ($testCases as $case) {
            $result = SalesCalculator::computeRow($case['data']);
            $this->assertEquals(
                $case['expected_settlement'],
                $result['settlement'],
                "케이스 '{$case['name']}' 실패"
            );
        }
    }
}