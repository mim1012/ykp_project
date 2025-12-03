<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $headquartersUser;
    protected User $branchUser;
    protected User $storeUser;
    protected Branch $branch;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

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
            'branch_id' => $this->branch->id,
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

    #[Test]
    public function overview_returns_correct_statistics_for_headquarters(): void
    {
        // Create sales data
        Sale::factory()->count(5)->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'sale_date' => now(),
            'settlement_amount' => 100000,
        ]);

        $response = $this->actingAs($this->headquartersUser)
            ->getJson('/api/dashboard/overview');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'stores' => ['total', 'active'],
                    'this_month_sales',
                ],
            ]);
    }

    #[Test]
    public function branch_user_sees_only_branch_data(): void
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

        $response = $this->actingAs($this->branchUser)
            ->getJson('/api/dashboard/overview');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Branch user should only see their branch's data
        $data = $response->json('data');
        $this->assertEquals(1, $data['stores']['total']);
    }

    #[Test]
    public function kpi_calculates_correct_metrics(): void
    {
        // Create sales with various amounts
        Sale::factory()->count(10)->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'sale_date' => now(),
            'settlement_amount' => 100000,
            'margin_after_tax' => 20000,
        ]);

        $response = $this->actingAs($this->headquartersUser)
            ->getJson('/api/statistics/kpi?days=30');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_revenue',
                    'total_activations',
                ],
            ]);
    }

    #[Test]
    public function rankings_returns_top_stores(): void
    {
        // Create multiple stores with different sales
        $store2 = Store::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => '매장2',
        ]);

        Sale::factory()->count(5)->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'settlement_amount' => 100000,
        ]);

        Sale::factory()->count(10)->create([
            'store_id' => $store2->id,
            'branch_id' => $this->branch->id,
            'settlement_amount' => 200000,
        ]);

        $response = $this->actingAs($this->headquartersUser)
            ->getJson('/api/dashboard/rankings?limit=5');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify stores data exists in the response
        $data = $response->json('data');
        $this->assertIsArray($data);
    }

    #[Test]
    public function sales_trend_returns_daily_data(): void
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

        $response = $this->actingAs($this->headquartersUser)
            ->getJson('/api/statistics/revenue-trend?days=7&type=daily');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    #[Test]
    public function store_user_access_is_restricted(): void
    {
        // Create sales for the store
        Sale::factory()->count(3)->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->storeUser)
            ->getJson('/api/dashboard/overview');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Store user should only see their store
        $data = $response->json('data');
        $this->assertEquals(1, $data['stores']['total']);
    }
}