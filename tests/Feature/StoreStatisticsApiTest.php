<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Goal;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreStatisticsApiTest extends TestCase
{
    use RefreshDatabase;

    protected $storeUser;
    protected $branchUser;
    protected $hqUser;
    protected $store;
    protected $branch;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test branch
        $this->branch = Branch::factory()->create([
            'code' => 'TEST',
            'name' => '테스트지사',
        ]);

        // Create test store
        $this->store = Store::factory()->create([
            'branch_id' => $this->branch->id,
            'code' => 'TEST-001',
            'name' => '테스트매장',
        ]);

        // Create test users
        $this->storeUser = User::factory()->create([
            'role' => 'store',
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'email' => 'test@store.com',
        ]);

        $this->branchUser = User::factory()->create([
            'role' => 'branch',
            'branch_id' => $this->branch->id,
            'email' => 'test@branch.com',
        ]);

        $this->hqUser = User::factory()->create([
            'role' => 'headquarters',
            'email' => 'test@hq.com',
        ]);
    }

    /** @test */
    public function can_get_daily_statistics()
    {
        // Create test sales for today
        Sale::factory()->count(5)->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'sale_date' => now()->format('Y-m-d'),
            'carrier' => 'SK',
            'activation_type' => '신규',
            'settlement_amount' => 100000,
        ]);

        Sale::factory()->count(3)->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'sale_date' => now()->format('Y-m-d'),
            'carrier' => 'KT',
            'activation_type' => '기변',
            'settlement_amount' => 150000,
        ]);

        $response = $this->actingAs($this->hqUser)
            ->getJson("/api/stores/{$this->store->id}/statistics?period=daily&date=" . now()->format('Y-m-d'));

        if ($response->status() !== 200) {
            echo "\n❌ Response status: " . $response->status() . "\n";
            echo "Response body: " . $response->getContent() . "\n";
        }

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                 ])
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'period',
                         'date',
                         'store' => ['id', 'name', 'code'],
                         'summary' => ['total_sales', 'total_settlement_amount'],
                         'carrier_distribution',
                         'activation_type_distribution',
                     ],
                 ]);

        $data = $response->json('data');
        $this->assertEquals(8, $data['summary']['total_sales']);
        $this->assertEquals('daily', $data['period']);

        echo "\n✅ Test passed: Can get daily statistics\n";
        echo "Total sales: {$data['summary']['total_sales']}, Amount: {$data['summary']['total_settlement_amount']}\n";
    }

    /** @test */
    public function can_get_monthly_statistics()
    {
        // Create test sales for this month
        Sale::factory()->count(10)->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'sale_date' => now()->format('Y-m-01'), // First day of month
            'carrier' => 'SK',
            'activation_type' => '신규',
            'settlement_amount' => 100000,
        ]);

        Sale::factory()->count(5)->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'sale_date' => now()->format('Y-m-15'), // Mid month
            'carrier' => 'LG',
            'activation_type' => '기변',
            'settlement_amount' => 120000,
        ]);

        $response = $this->actingAs($this->hqUser)
            ->getJson("/api/stores/{$this->store->id}/statistics?period=monthly&year=" . now()->year . "&month=" . now()->month);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'period',
                         'year',
                         'month',
                         'summary',
                         'carrier_distribution',
                         'activation_type_distribution',
                         'daily_breakdown',
                     ],
                 ]);

        $data = $response->json('data');
        $this->assertEquals(15, $data['summary']['total_sales']);

        echo "\n✅ Test passed: Can get monthly statistics\n";
        echo "Total sales: {$data['summary']['total_sales']}\n";
    }

    /** @test */
    public function can_get_yearly_statistics()
    {
        $currentYear = now()->year;

        // Create test sales for this year
        Sale::factory()->count(20)->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'sale_date' => "{$currentYear}-01-15",
            'carrier' => 'SK',
            'activation_type' => '신규',
            'settlement_amount' => 100000,
        ]);

        Sale::factory()->count(15)->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'sale_date' => "{$currentYear}-07-15",
            'carrier' => 'KT',
            'activation_type' => '기변',
            'settlement_amount' => 110000,
        ]);

        $response = $this->actingAs($this->hqUser)
            ->getJson("/api/stores/{$this->store->id}/statistics?period=yearly&year={$currentYear}");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'period',
                         'year',
                         'summary',
                         'carrier_distribution',
                         'activation_type_distribution',
                         'monthly_breakdown',
                     ],
                 ]);

        $data = $response->json('data');
        $this->assertEquals(35, $data['summary']['total_sales']);

        echo "\n✅ Test passed: Can get yearly statistics\n";
    }

    /** @test */
    public function statistics_includes_goal_achievement_when_goal_exists()
    {
        // Create a goal for this month
        Goal::factory()->create([
            'store_id' => $this->store->id,
            'target_month' => now()->format('Y-m-01'),
            'sales_target' => 5000000,
            'activation_target' => 50,
            'margin_target' => 1000000,
        ]);

        // Create some sales
        Sale::factory()->count(10)->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'sale_date' => now()->format('Y-m-d'),
            'activation_type' => '신규',
            'settlement_amount' => 100000,
        ]);

        $response = $this->actingAs($this->hqUser)
            ->getJson("/api/stores/{$this->store->id}/statistics?period=monthly&year=" . now()->year . "&month=" . now()->month);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertNotNull($data['goal_achievement']);
        $this->assertEquals(5000000, $data['goal_achievement']['sales_target']);
        $this->assertEquals(50, $data['goal_achievement']['activation_target']);

        echo "\n✅ Test passed: Goal achievement included in statistics\n";
        echo "Achievement rate: {$data['goal_achievement']['sales_achievement_rate']}%\n";
    }

    /** @test */
    public function store_user_can_only_view_own_statistics()
    {
        $response = $this->actingAs($this->storeUser)
            ->getJson("/api/stores/{$this->store->id}/statistics?period=daily");

        $response->assertStatus(200);

        // Try to access another store
        $otherStore = Store::factory()->create(['branch_id' => $this->branch->id]);
        $response2 = $this->actingAs($this->storeUser)
            ->getJson("/api/stores/{$otherStore->id}/statistics?period=daily");

        $response2->assertStatus(403);

        echo "\n✅ Test passed: Store user RBAC working correctly\n";
    }

    /** @test */
    public function branch_user_can_only_view_branch_stores()
    {
        $response = $this->actingAs($this->branchUser)
            ->getJson("/api/stores/{$this->store->id}/statistics?period=daily");

        $response->assertStatus(200);

        // Try to access store from another branch
        $otherBranch = Branch::factory()->create(['code' => 'OTHER']);
        $otherStore = Store::factory()->create(['branch_id' => $otherBranch->id]);

        $response2 = $this->actingAs($this->branchUser)
            ->getJson("/api/stores/{$otherStore->id}/statistics?period=daily");

        $response2->assertStatus(403);

        echo "\n✅ Test passed: Branch user RBAC working correctly\n";
    }

    /** @test */
    public function hq_user_can_view_all_stores()
    {
        $response = $this->actingAs($this->hqUser)
            ->getJson("/api/stores/{$this->store->id}/statistics?period=daily");

        $response->assertStatus(200);

        // Create and access another store from different branch
        $otherBranch = Branch::factory()->create(['code' => 'OTHER']);
        $otherStore = Store::factory()->create(['branch_id' => $otherBranch->id]);

        $response2 = $this->actingAs($this->hqUser)
            ->getJson("/api/stores/{$otherStore->id}/statistics?period=daily");

        $response2->assertStatus(200);

        echo "\n✅ Test passed: HQ user can view all stores\n";
    }

    /** @test */
    public function carrier_distribution_is_accurate()
    {
        Sale::factory()->count(10)->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'sale_date' => now()->format('Y-m-d'),
            'carrier' => 'SK',
            'activation_type' => '신규',
        ]);

        Sale::factory()->count(5)->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'sale_date' => now()->format('Y-m-d'),
            'carrier' => 'KT',
            'activation_type' => '기변',
        ]);

        Sale::factory()->count(3)->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'sale_date' => now()->format('Y-m-d'),
            'carrier' => 'LG',
            'activation_type' => '신규',
        ]);

        $response = $this->actingAs($this->hqUser)
            ->getJson("/api/stores/{$this->store->id}/statistics?period=daily&date=" . now()->format('Y-m-d'));

        $data = $response->json('data');
        $this->assertEquals(10, $data['carrier_distribution']['SK']);
        $this->assertEquals(5, $data['carrier_distribution']['KT']);
        $this->assertEquals(3, $data['carrier_distribution']['LG']);

        echo "\n✅ Test passed: Carrier distribution accurate\n";
    }

    /** @test */
    public function activation_type_distribution_is_accurate()
    {
        Sale::factory()->count(7)->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'sale_date' => now()->format('Y-m-d'),
            'activation_type' => '신규',
        ]);

        Sale::factory()->count(4)->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'sale_date' => now()->format('Y-m-d'),
            'activation_type' => '번이',
        ]);

        Sale::factory()->count(2)->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'sale_date' => now()->format('Y-m-d'),
            'activation_type' => '기변',
        ]);

        $response = $this->actingAs($this->hqUser)
            ->getJson("/api/stores/{$this->store->id}/statistics?period=daily&date=" . now()->format('Y-m-d'));

        $data = $response->json('data');
        $this->assertEquals(7, $data['activation_type_distribution']['신규']);
        $this->assertEquals(4, $data['activation_type_distribution']['번이']);
        $this->assertEquals(2, $data['activation_type_distribution']['기변']);

        echo "\n✅ Test passed: Activation type distribution accurate\n";
    }
}
