<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that unauthenticated users cannot change password.
     */
    public function test_unauthenticated_users_cannot_change_password(): void
    {
        $response = $this->postJson('/api/users/change-password', [
            'current_password' => 'old-password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test that authenticated users can change their password with valid data.
     */
    public function test_authenticated_users_can_change_password_with_valid_data(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword123!'),
        ]);

        $response = $this->actingAs($user)->postJson('/api/users/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        // Verify password was updated
        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
    }

    /**
     * Test that password change fails with incorrect current password.
     */
    public function test_password_change_fails_with_incorrect_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword123!'),
        ]);

        $response = $this->actingAs($user)->postJson('/api/users/change-password', [
            'current_password' => 'WrongPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['current_password']);
    }

    /**
     * Test that password change fails when new password is too short.
     */
    public function test_password_change_fails_when_new_password_is_too_short(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword123!'),
        ]);

        $response = $this->actingAs($user)->postJson('/api/users/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'Short1!',
            'password_confirmation' => 'Short1!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    /**
     * Test that password change fails when password lacks letters.
     */
    public function test_password_change_fails_when_password_lacks_letters(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword123!'),
        ]);

        $response = $this->actingAs($user)->postJson('/api/users/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => '12345678!@#',
            'password_confirmation' => '12345678!@#',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    /**
     * Test that password change fails when password lacks numbers.
     */
    public function test_password_change_fails_when_password_lacks_numbers(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword123!'),
        ]);

        $response = $this->actingAs($user)->postJson('/api/users/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NoNumbers!@#',
            'password_confirmation' => 'NoNumbers!@#',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    /**
     * Test that password change fails when password lacks symbols.
     */
    public function test_password_change_fails_when_password_lacks_symbols(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword123!'),
        ]);

        $response = $this->actingAs($user)->postJson('/api/users/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NoSymbols123',
            'password_confirmation' => 'NoSymbols123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    /**
     * Test that password change fails when confirmation doesn't match.
     */
    public function test_password_change_fails_when_confirmation_does_not_match(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword123!'),
        ]);

        $response = $this->actingAs($user)->postJson('/api/users/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    /**
     * Test that all roles (headquarters, branch, store) can change their own password.
     */
    public function test_all_roles_can_change_their_own_password(): void
    {
        $roles = ['headquarters', 'branch', 'store'];

        foreach ($roles as $role) {
            $user = User::factory()->create([
                'role' => $role,
                'password' => Hash::make('OldPassword123!'),
            ]);

            $response = $this->actingAs($user)->postJson('/api/users/change-password', [
                'current_password' => 'OldPassword123!',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ]);

            $response->assertStatus(200);
            $response->assertJson([
                'success' => true,
            ]);

            // Verify password was updated
            $user->refresh();
            $this->assertTrue(Hash::check('NewPassword123!', $user->password));
        }
    }

    /**
     * Test that password change requires all fields.
     */
    public function test_password_change_requires_all_fields(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword123!'),
        ]);

        // Missing current_password
        $response = $this->actingAs($user)->postJson('/api/users/change-password', [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['current_password']);

        // Missing password
        $response = $this->actingAs($user)->postJson('/api/users/change-password', [
            'current_password' => 'OldPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);

        // Missing password_confirmation
        $response = $this->actingAs($user)->postJson('/api/users/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }
}
