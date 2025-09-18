<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;
    /**
     * Test that the application redirects to login when not authenticated.
     */
    public function test_the_application_redirects_to_login_when_not_authenticated(): void
    {
        $response = $this->get('/');

        // Expecting redirect to login page
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test that authenticated users can access the application.
     */
    public function test_authenticated_users_can_access_application(): void
    {
        // Create a user and authenticate
        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }
}
