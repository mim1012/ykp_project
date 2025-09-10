<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 테스트 데이터 생성
        $this->createTestData();
    }

    private function createTestData(): void
    {
        // 지사 생성
        $branch = Branch::create([
            'code' => 'TEST001',
            'name' => '테스트지사',
            'manager_name' => '김테스트',
            'phone' => '02-123-4567',
            'status' => 'active'
        ]);

        // 매장 생성
        $store = Store::create([
            'branch_id' => $branch->id,
            'code' => 'TEST001-001',
            'name' => '테스트매장',
            'owner_name' => '박사장',
            'phone' => '010-1234-5678',
            'status' => 'active'
        ]);

        // 판매 데이터 생성
        Sale::create([
            'dealer_code' => 'TEST001',
            'store_id' => $store->id,
            'branch_id' => $branch->id,
            'sale_date' => now()->format('Y-m-d'),
            'carrier' => 'KT',
            'activation_type' => '신규',
            'model_name' => 'Test Phone',
            'base_price' => 500000,
            'verbal1' => 100000,
            'verbal2' => 50000,
            'rebate_total' => 650000,
            'settlement_amount' => 400000,
            'tax' => 40000,
            'margin_before_tax' => 360000,
            'margin_after_tax' => 320000,
            'monthly_fee' => 80000
        ]);
    }

    /** @test */
    public function goal_progress_api_returns_correct_structure()
    {
        $response = $this->get('/api/statistics/goal-progress');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'monthly_revenue' => ['current', 'target', 'achievement'],
                         'monthly_activations' => ['current', 'target', 'achievement'],
                         'profit_rate' => ['current', 'target', 'achievement'],
                         'meta'
                     ]
                 ]);
    }

    /** @test */
    public function goal_progress_api_handles_empty_data()
    {
        // 모든 판매 데이터 삭제
        Sale::query()->delete();

        $response = $this->get('/api/statistics/goal-progress');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertEquals(0, $data['monthly_revenue']['current']);
        $this->assertEquals(0, $data['monthly_activations']['current']);
        $this->assertEquals(0, $data['profit_rate']['current']);
    }

    /** @test */
    public function goal_progress_api_filters_by_store()
    {
        $store = Store::first();
        
        $response = $this->get("/api/statistics/goal-progress?store={$store->id}");

        $response->assertStatus(200);
        
        $meta = $response->json('data.meta');
        $this->assertTrue($meta['is_store_view']);
        $this->assertEquals($store->id, $meta['store_filter']['id']);
    }

    /** @test */
    public function kpi_api_works_with_real_data()
    {
        $response = $this->get('/api/statistics/kpi?days=30');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'total_revenue',
                         'net_profit',
                         'profit_margin',
                         'total_activations',
                         'avg_daily',
                         'active_stores',
                         'revenue_growth',
                         'period',
                         'store_filter'
                     ],
                     'meta'
                 ]);

        // 실제 계산된 데이터 확인
        $data = $response->json('data');
        $this->assertEquals(400000, $data['total_revenue']); // 테스트 데이터 정산금액
        $this->assertEquals(320000, $data['net_profit']);    // 테스트 데이터 마진
    }

    /** @test */
    public function branch_performance_api_optimized_query_count()
    {
        // 쿼리 카운트 측정을 위한 테스트
        \DB::enableQueryLog();

        $response = $this->get('/api/statistics/branch-performance?days=30');

        $queryLog = \DB::getQueryLog();
        \DB::disableQueryLog();

        $response->assertStatus(200);
        
        // N+1 쿼리 문제 해결 확인: 최대 5개 쿼리 이하
        $this->assertLessThanOrEqual(5, count($queryLog), 'N+1 쿼리 문제 발생');
        
        $meta = $response->json('meta');
        $this->assertEquals(3, $meta['query_count']); // 명시된 쿼리 수와 일치
    }
}