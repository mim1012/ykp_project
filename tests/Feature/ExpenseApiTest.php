<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseApiTest extends TestCase
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
    public function store_user_can_create_expense()
    {
        $response = $this->actingAs($this->storeUser)
            ->postJson('/api/expenses', [
                'expense_date' => '2025-11-20',
                'description' => '매장 임대료',
                'amount' => 1500000,
            ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Expense created successfully',
                 ]);

        $this->assertDatabaseHas('expenses', [
            'store_id' => $this->store->id,
            'description' => '매장 임대료',
            'amount' => 1500000,
        ]);

        echo "\n✅ Test passed: Store user can create expense\n";
    }

    /** @test */
    public function branch_user_cannot_create_expense()
    {
        $response = $this->actingAs($this->branchUser)
            ->postJson('/api/expenses', [
                'expense_date' => '2025-11-20',
                'description' => '테스트 지출',
                'amount' => 100000,
            ]);

        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Only store users can create expenses',
                 ]);

        echo "\n✅ Test passed: Branch user cannot create expense\n";
    }

    /** @test */
    public function store_user_can_get_their_expenses()
    {
        // Create test expenses
        Expense::factory()->count(5)->create([
            'store_id' => $this->store->id,
        ]);

        $response = $this->actingAs($this->storeUser)
            ->getJson('/api/expenses');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                 ])
                 ->assertJsonStructure([
                     'success',
                     'data',
                     'meta' => ['current_page', 'total'],
                 ]);

        echo "\n✅ Test passed: Store user can get their expenses\n";
        echo "Total expenses: " . count($response->json('data')) . "\n";
    }

    /** @test */
    public function store_user_can_update_their_expense()
    {
        $expense = Expense::factory()->create([
            'store_id' => $this->store->id,
            'description' => '원래 내용',
            'amount' => 50000,
        ]);

        $response = $this->actingAs($this->storeUser)
            ->putJson("/api/expenses/{$expense->id}", [
                'description' => '수정된 내용',
                'amount' => 60000,
            ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Expense updated successfully',
                 ]);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'description' => '수정된 내용',
            'amount' => 60000,
        ]);

        echo "\n✅ Test passed: Store user can update their expense\n";
    }

    /** @test */
    public function branch_user_cannot_update_expense()
    {
        $expense = Expense::factory()->create([
            'store_id' => $this->store->id,
        ]);

        $response = $this->actingAs($this->branchUser)
            ->putJson("/api/expenses/{$expense->id}", [
                'description' => '수정 시도',
            ]);

        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Only store users can update expenses',
                 ]);

        echo "\n✅ Test passed: Branch user cannot update expense\n";
    }

    /** @test */
    public function store_user_can_delete_their_expense()
    {
        $expense = Expense::factory()->create([
            'store_id' => $this->store->id,
        ]);

        $response = $this->actingAs($this->storeUser)
            ->deleteJson("/api/expenses/{$expense->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Expense deleted successfully',
                 ]);

        $this->assertDatabaseMissing('expenses', [
            'id' => $expense->id,
        ]);

        echo "\n✅ Test passed: Store user can delete their expense\n";
    }

    /** @test */
    public function store_user_can_get_monthly_summary()
    {
        // Create expenses for current month
        Expense::factory()->count(3)->create([
            'store_id' => $this->store->id,
            'expense_date' => '2025-11-15',
            'amount' => 100000,
        ]);

        $response = $this->actingAs($this->storeUser)
            ->getJson('/api/expenses/summary?year=2025&month=11');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                 ])
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'period',
                         'type',
                         'count',
                         'total',
                         'average',
                     ],
                 ]);

        $data = $response->json('data');
        echo "\n✅ Test passed: Monthly summary\n";
        echo "Period: {$data['period']}, Count: {$data['count']}, Total: {$data['total']}\n";
    }

    /** @test */
    public function branch_user_can_view_branch_expenses()
    {
        // Create expenses for stores in this branch
        Expense::factory()->count(5)->create([
            'store_id' => $this->store->id,
        ]);

        $response = $this->actingAs($this->branchUser)
            ->getJson('/api/expenses');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                 ]);

        echo "\n✅ Test passed: Branch user can view branch expenses\n";
        echo "Total expenses visible: " . count($response->json('data')) . "\n";
    }

    /** @test */
    public function hq_user_can_view_all_expenses()
    {
        // Create expenses for this store
        Expense::factory()->count(3)->create([
            'store_id' => $this->store->id,
        ]);

        $response = $this->actingAs($this->hqUser)
            ->getJson('/api/expenses');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                 ]);

        echo "\n✅ Test passed: HQ user can view all expenses\n";
    }
}
