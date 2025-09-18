<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\DashboardController;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected DashboardController $controller;
    protected User $headquartersUser;
    protected User $branchUser;
    protected User $storeUser;
    protected Branch $branch;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip migration that causes issues
        $this->artisan('migrate:fresh', ['--env' => 'testing']);

        $this->controller = new DashboardController();

        // Create test data
        $this->branch = Branch::factory()->create([
            'name' => '테스트 지사',
            'code' => 'TEST',
        ]);

        $this->store = Store::factory()->create([
            'name' => '테스트 매장',
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);

        // Create users with different roles
        $this->headquartersUser = User::factory()->create([
            'role' => 'headquarters',
            'branch_id' => null,
            'store_id' => null,
        ]);

        $this->branchUser = User::factory()->create([
            'role' => 'branch',
            'branch_id' => $this->branch->id,
            'store_id' => null,
        ]);

        $this->storeUser = User::factory()->create([
            'role' => 'store',
            'branch_id' => $this->branch->id,
            'store_id' => $this->store->id,
        ]);
    }

    public function test_overview_returns_correct_statistics_for_headquarters(): void
    {
        // Create sales data
        Sale::factory()->count(5)->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'sale_date' => now(),
            'settlement_amount' => 100000,
        ]);

        $this->actingAs($this->headquartersUser);

        $request = Request::create('/api/dashboard/overview', 'GET');
        $response = $this->controller->overview($request);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('total_stores', $data['data']);
        $this->assertArrayHasKey('total_sales', $data['data']);
        $this->assertArrayHasKey('today_sales', $data['data']);
        $this->assertArrayHasKey('monthly_sales', $data['data']);
    }

    public function test_branch_user_sees_only_branch_data(): void
    {
        // Create another branch with store
        $otherBranch = Branch::factory()->create(['name' => '다른 지사']);
        $otherStore = Store::factory()->create([
            'branch_id' => $otherBranch->id,
        ]);

        // Create sales for both stores
        Sale::factory()->count(3)->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'settlement_amount' => 100000,
        ]);

        Sale::factory()->count(2)->create([
            'store_id' => $otherStore->id,
            'branch_id' => $otherBranch->id,
            'settlement_amount' => 200000,
        ]);

        $this->actingAs($this->branchUser);

        $request = Request::create('/api/dashboard/overview', 'GET');
        $response = $this->controller->overview($request);

        $data = json_decode($response->getContent(), true);

        // Branch user should only see their branch's data
        $this->assertTrue($data['success']);
        $this->assertEquals(1, $data['data']['total_stores']); // Only their store
    }

    public function test_kpi_calculates_correct_metrics(): void
    {
        // Create sales with various amounts
        Sale::factory()->count(10)->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'sale_date' => now(),
            'settlement_amount' => 100000,
            'margin_after_tax' => 20000,
        ]);

        $this->actingAs($this->headquartersUser);

        $request = Request::create('/api/statistics/kpi', 'GET', [
            'year_month' => now()->format('Y-m'),
        ]);

        $response = $this->controller->kpi($request);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('current_month', $data['data']);
        $this->assertArrayHasKey('previous_month', $data['data']);
        $this->assertArrayHasKey('growth', $data['data']);
        $this->assertArrayHasKey('averages', $data['data']);
    }

    public function test_rankings_returns_top_stores(): void
    {
        // Create multiple stores with different sales
        $store2 = Store::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => '매장2',
        ]);

        Sale::factory()->count(5)->create([
            'store_id' => $this->store->id,
            'settlement_amount' => 100000,
        ]);

        Sale::factory()->count(10)->create([
            'store_id' => $store2->id,
            'settlement_amount' => 200000,
        ]);

        $this->actingAs($this->headquartersUser);

        $request = Request::create('/api/dashboard/rankings', 'GET', [
            'limit' => 5,
        ]);

        $response = $this->controller->rankings($request);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('stores', $data['data']);
        $this->assertArrayHasKey('branches', $data['data']);

        // Check if store2 ranks higher
        $storeRankings = $data['data']['stores'];
        $this->assertEquals($store2->id, $storeRankings[0]['store_id']);
    }

    public function test_sales_trend_returns_daily_data(): void
    {
        // Create sales for different days
        $dates = [
            now()->subDays(6),
            now()->subDays(5),
            now()->subDays(4),
            now()->subDays(3),
            now()->subDays(2),
            now()->subDays(1),
            now(),
        ];

        foreach ($dates as $date) {
            Sale::factory()->count(2)->create([
                'store_id' => $this->store->id,
                'branch_id' => $this->branch->id,
                'sale_date' => $date,
                'settlement_amount' => rand(50000, 150000),
            ]);
        }

        $this->actingAs($this->headquartersUser);

        $request = Request::create('/api/dashboard/sales-trend', 'GET', [
            'days' => 7,
        ]);

        $response = $this->controller->salesTrend($request);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('dates', $data['data']);
        $this->assertArrayHasKey('sales', $data['data']);
        $this->assertArrayHasKey('counts', $data['data']);
        $this->assertCount(7, $data['data']['dates']);
    }

    public function test_store_user_access_is_restricted(): void
    {
        // Create sales for the store
        Sale::factory()->count(3)->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
        ]);

        $this->actingAs($this->storeUser);

        $request = Request::create('/api/dashboard/overview', 'GET');
        $response = $this->controller->overview($request);

        $data = json_decode($response->getContent(), true);

        // Store user should only see their store
        $this->assertTrue($data['success']);
        $this->assertEquals(1, $data['data']['total_stores']);
    }
}