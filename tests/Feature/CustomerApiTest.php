<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    protected $storeUser;
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

        // Create test store user
        $this->storeUser = User::factory()->create([
            'role' => 'store',
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'email' => 'test@store.com',
        ]);
    }

    #[Test]
    public function store_user_can_create_prospect_customer()
    {
        $response = $this->actingAs($this->storeUser)
            ->postJson('/api/customers', [
                'phone_number' => '010-1234-5678',
                'customer_name' => '홍길동',
                'birth_date' => '1990-01-01',
                'current_device' => '갤럭시 S21',
                'first_visit_date' => now()->format('Y-m-d'),
                'notes' => '상담 내용 메모',
            ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Prospect customer created successfully',
                 ]);

        $this->assertDatabaseHas('customers', [
            'phone_number' => '010-1234-5678',
            'customer_name' => '홍길동',
            'customer_type' => 'prospect',
            'store_id' => $this->store->id,
        ]);

        echo "\nTest passed: Store user can create prospect customer\n";
        echo "Response: " . $response->getContent() . "\n";
    }

    #[Test]
    public function store_user_can_get_their_customers()
    {
        // Create test customers
        Customer::factory()->count(3)->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'customer_type' => 'prospect',
        ]);

        $response = $this->actingAs($this->storeUser)
            ->getJson('/api/customers');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                 ])
                 ->assertJsonStructure([
                     'success',
                     'data',
                     'meta' => ['current_page', 'total'],
                 ]);

        echo "\nTest passed: Store user can get their customers\n";
        echo "Total customers: " . count($response->json('data')) . "\n";
    }

    #[Test]
    public function store_user_can_update_customer()
    {
        $customer = Customer::factory()->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'customer_type' => 'prospect',
            'customer_name' => '김철수',
        ]);

        $response = $this->actingAs($this->storeUser)
            ->putJson("/api/customers/{$customer->id}", [
                'customer_name' => '김철수 (수정)',
                'notes' => '업데이트된 메모',
            ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Customer updated successfully',
                 ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'customer_name' => '김철수 (수정)',
            'notes' => '업데이트된 메모',
        ]);

        echo "\nTest passed: Store user can update customer\n";
    }

    #[Test]
    public function store_user_can_delete_prospect_customer()
    {
        $customer = Customer::factory()->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'customer_type' => 'prospect',
        ]);

        $response = $this->actingAs($this->storeUser)
            ->deleteJson("/api/customers/{$customer->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Customer deleted successfully',
                 ]);

        $this->assertDatabaseMissing('customers', [
            'id' => $customer->id,
        ]);

        echo "\nTest passed: Store user can delete prospect customer\n";
    }

    #[Test]
    public function store_user_cannot_create_duplicate_customer()
    {
        // Create existing customer
        Customer::factory()->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'phone_number' => '010-9999-8888',
        ]);

        // Try to create duplicate
        $response = $this->actingAs($this->storeUser)
            ->postJson('/api/customers', [
                'phone_number' => '010-9999-8888',
                'customer_name' => '중복고객',
            ]);

        $response->assertStatus(409) // Conflict
                 ->assertJson([
                     'success' => false,
                     'message' => 'Customer with this phone number already exists',
                 ]);

        echo "\nTest passed: Duplicate customer prevention works\n";
    }

    #[Test]
    public function store_user_can_get_customer_statistics()
    {
        // Create test data
        Customer::factory()->count(5)->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'customer_type' => 'prospect',
        ]);

        Customer::factory()->count(3)->create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'customer_type' => 'activated',
        ]);

        $response = $this->actingAs($this->storeUser)
            ->getJson('/api/customers/statistics');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                 ])
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'total_customers',
                         'prospects',
                         'activated',
                         'conversion_rate',
                     ],
                 ]);

        $stats = $response->json('data');
        echo "\nTest passed: Customer statistics\n";
        echo "Total: {$stats['total_customers']}, Prospects: {$stats['prospects']}, Activated: {$stats['activated']}\n";
        echo "Conversion Rate: {$stats['conversion_rate']}%\n";
    }
}
