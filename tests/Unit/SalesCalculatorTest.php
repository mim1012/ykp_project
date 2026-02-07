<?php

namespace Tests\Unit;

use App\Helpers\SalesCalculator;
use PHPUnit\Framework\TestCase;

class SalesCalculatorTest extends TestCase
{
    public function test_정상적인_리베이트_총계_계산(): void
    {
        $data = [
            'price_setting' => 10000,
            'verbal1' => 2000,
            'verbal2' => 1000,
            'grade_amount' => 500,
            'addon_amount' => 300,
        ];

        $result = SalesCalculator::computeRow($data);

        $this->assertEquals(13800.0, $result['total_rebate']);
    }

    public function test_정산금_계산_서류상현금개통_차감(): void
    {
        $data = [
            'price_setting' => 10000,
            'verbal1' => 2000,
            'paper_cash' => 5000,
            'usim_fee' => 300,
            'new_mnp_disc' => 1000,
            'deduction' => 500,
        ];

        $result = SalesCalculator::computeRow($data);

        // 차감 수정: 12000 - 5000 + 300 + 1000 - 500 = 7800
        $this->assertEquals(7800.0, $result['settlement']);
    }

    public function test_세금_계산_10퍼센트(): void
    {
        $data = [
            'price_setting' => 10000,
        ];

        $result = SalesCalculator::computeRow($data);

        // 세금 계산이 제거됨 (커밋 427845b6: 세금 계산 제거 및 마진=정산금으로 통일)
        $this->assertEquals(0.0, $result['tax'], '세금은 0이어야 함');
        $this->assertEquals($result['settlement'], $result['margin_before'], '마진은 정산금과 동일해야 함');
        $this->assertEquals($result['settlement'], $result['margin_after'], '마진은 정산금과 동일해야 함');
    }

    public function test_데이터_검증_필수_필드_누락(): void
    {
        $data = [
            'price_setting' => 10000,
            // seller 누락
            // opened_on 누락
        ];

        $result = SalesCalculator::validateRow($data);

        $this->assertFalse($result['valid']);
        $this->assertContains('판매자가 누락되었습니다', $result['errors']);
        $this->assertContains('개통일이 누락되었습니다', $result['errors']);
    }

    public function test_데이터_검증_음수_값_체크(): void
    {
        $data = [
            'seller' => '홍길동',
            'opened_on' => '2024-01-01',
            'price_setting' => -1000, // 음수 불가
            'new_mnp_disc' => -500,   // 음수 허용
        ];

        $result = SalesCalculator::validateRow($data);

        $this->assertFalse($result['valid']);
        $this->assertContains('price_setting 필드는 음수가 될 수 없습니다', $result['errors']);
    }

    public function test_월간_요약_통계_계산(): void
    {
        $salesData = [
            [
                'price_setting' => 10000,
                'verbal1' => 1000,
                'cash_in' => 500,
                'payback' => 200,
            ],
            [
                'price_setting' => 20000,
                'verbal1' => 2000,
                'cash_in' => 1000,
                'payback' => 400,
            ],
        ];

        $summary = SalesCalculator::computeMonthlySummary($salesData);

        $this->assertEquals(2, $summary['total_units']);
        $this->assertGreaterThan(0, $summary['avg_margin']);
        $this->assertGreaterThan(0, $summary['total_settlement']);
    }
}
