<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_application_redirects_to_login_when_not_authenticated(): void
    {
        $response = $this->get('/');

        // Expecting redirect to login page
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    #[Test]
    public function authenticated_users_can_access_dashboard(): void
    {
        // Create a user with proper role
        $user = User::factory()->create([
            'role' => 'headquarters',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        // Dashboard should return 200 or redirect to dashboard view
        $response->assertSuccessful();
    }

    #[Test]
    public function login_page_is_accessible(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }
}
