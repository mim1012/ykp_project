<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StoreClassificationApiTest extends TestCase
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
            'store_type' => 'franchise',
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

    #[Test]
    public function branch_user_can_update_store_classification()
    {
        $response = $this->actingAs($this->branchUser)
            ->putJson("/api/stores/{$this->store->id}/classification", [
                'store_type' => 'direct',
            ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Store classification updated successfully',
                 ]);

        $this->assertDatabaseHas('stores', [
            'id' => $this->store->id,
            'store_type' => 'direct',
        ]);

        echo "\nTest passed: Branch user can update store classification\n";
    }

    #[Test]
    public function hq_user_can_update_store_classification()
    {
        $response = $this->actingAs($this->hqUser)
            ->putJson("/api/stores/{$this->store->id}/classification", [
                'store_type' => 'direct',
            ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                 ]);

        echo "\nTest passed: HQ user can update store classification\n";
    }

    #[Test]
    public function store_user_cannot_update_classification()
    {
        $response = $this->actingAs($this->storeUser)
            ->putJson("/api/stores/{$this->store->id}/classification", [
                'store_type' => 'direct',
            ]);

        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Store users cannot update store classification',
                 ]);

        // Store type should remain unchanged
        $this->assertDatabaseHas('stores', [
            'id' => $this->store->id,
            'store_type' => 'franchise',
        ]);

        echo "\nTest passed: Store user cannot update classification (RBAC)\n";
    }

    #[Test]
    public function branch_user_cannot_update_other_branch_store()
    {
        // Create another branch and store
        $otherBranch = Branch::factory()->create(['code' => 'OTHER']);
        $otherStore = Store::factory()->create(['branch_id' => $otherBranch->id]);

        $response = $this->actingAs($this->branchUser)
            ->putJson("/api/stores/{$otherStore->id}/classification", [
                'store_type' => 'direct',
            ]);

        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'message' => 'You can only update stores in your branch',
                 ]);

        echo "\nTest passed: Branch user cannot update other branch stores (RBAC)\n";
    }

    #[Test]
    public function classification_only_accepts_valid_values()
    {
        $response = $this->actingAs($this->hqUser)
            ->putJson("/api/stores/{$this->store->id}/classification", [
                'store_type' => 'invalid_type',
            ]);

        $response->assertStatus(422); // Validation error

        echo "\nTest passed: Invalid store_type rejected\n";
    }

    #[Test]
    public function branch_user_can_update_business_info()
    {
        $response = $this->actingAs($this->branchUser)
            ->putJson("/api/stores/{$this->store->id}/business-info", [
                'business_registration_number' => '123-45-67890',
                'email' => 'store@example.com',
            ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Store business information updated successfully',
                 ]);

        $this->assertDatabaseHas('stores', [
            'id' => $this->store->id,
            'business_registration_number' => '123-45-67890',
            'email' => 'store@example.com',
        ]);

        echo "\nTest passed: Branch user can update business info\n";
    }

    #[Test]
    public function hq_user_can_update_business_info()
    {
        $response = $this->actingAs($this->hqUser)
            ->putJson("/api/stores/{$this->store->id}/business-info", [
                'business_registration_number' => '999-99-99999',
                'email' => 'hq-updated@example.com',
            ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                 ]);

        echo "\nTest passed: HQ user can update business info\n";
    }

    #[Test]
    public function store_user_cannot_update_business_info()
    {
        $response = $this->actingAs($this->storeUser)
            ->putJson("/api/stores/{$this->store->id}/business-info", [
                'business_registration_number' => '000-00-00000',
            ]);

        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Store users cannot update business information',
                 ]);

        echo "\nTest passed: Store user cannot update business info (RBAC)\n";
    }

    #[Test]
    public function business_info_fields_are_optional()
    {
        // Update only email
        $response = $this->actingAs($this->hqUser)
            ->putJson("/api/stores/{$this->store->id}/business-info", [
                'email' => 'only-email@example.com',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('stores', [
            'id' => $this->store->id,
            'email' => 'only-email@example.com',
        ]);

        // Update only business registration number
        $response2 = $this->actingAs($this->hqUser)
            ->putJson("/api/stores/{$this->store->id}/business-info", [
                'business_registration_number' => '111-11-11111',
            ]);

        $response2->assertStatus(200);

        echo "\nTest passed: Business info fields are optional\n";
    }

    #[Test]
    public function email_validation_works()
    {
        $response = $this->actingAs($this->hqUser)
            ->putJson("/api/stores/{$this->store->id}/business-info", [
                'email' => 'invalid-email-format',
            ]);

        $response->assertStatus(422); // Validation error

        echo "\nTest passed: Invalid email format rejected\n";
    }
}
