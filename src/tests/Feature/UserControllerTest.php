<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_list_users(): void
    {
        // Create an admin user to authenticate
        $admin = User::factory()->create(['role' => 'admin']);

        // Create sample users (must exist before calling API)
        User::factory()->count(3)->create();

        // Authenticate as admin and call the endpoint
        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/user/list', [
                'filters' => ['name' => ''],
                'sort'    => ['field' => 'name', 'direction' => 'asc'],
                'limit'   => 10,
            ]);

        // Ensure response is successful and paginated
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'email']
            ],
            'current_page',
            'total',
            'per_page',
            'last_page',
        ]);

        // Ensure at least 3 users exist in the response
        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }


    /*
    |--------------------------------------------------------------------------
    | TEST: List Users (POST /api/user/list)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_can_get_a_user_by_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $response = $this->actingAs($admin, 'api')
            ->getJson("/api/user/{$user->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TEST: Show User (GET /api/user/{id})
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_returns_404_when_user_not_found(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'api')
            ->getJson("/api/user/999999");

        $response->assertStatus(404);
        $response->assertJson(['message' => 'User not found']);
    }

    #[Test]
    public function it_allows_admin_to_create_a_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/user', [
                'name'     => 'New User',
                'email'    => 'newuser@example.com',
                'password' => 'password123',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    /*
    |--------------------------------------------------------------------------
    | TEST: Create User (POST /api/user)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_prevents_non_admins_from_creating_users(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/user', [
                'name'     => 'Unauthorized User',
                'email'    => 'unauthorized@example.com',
                'password' => 'password123',
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_allows_admin_or_user_to_update_their_own_profile(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);

        // Admin updates another user
        $responseAdmin = $this->actingAs($admin, 'api')
            ->putJson("/api/user/{$user->id}", ['name' => 'Updated Name']);
        $responseAdmin->assertStatus(200);

        // User updates their own profile
        $responseUser = $this->actingAs($user, 'api')
            ->putJson("/api/user/{$user->id}", ['name' => 'Self Updated']);
        $responseUser->assertStatus(200);
    }

    /*
    |--------------------------------------------------------------------------
    | TEST: Update User (PUT /api/user/{id})
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_prevents_users_from_updating_others_profiles(): void
    {
        $user1 = User::factory()->create(['role' => 'user']);
        $user2 = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user1, 'api')
            ->putJson("/api/user/{$user2->id}", ['name' => 'Hacked Name']);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_allows_admin_to_delete_a_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $response = $this->actingAs($admin, 'api')
            ->deleteJson("/api/user/{$user->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | TEST: Delete User (DELETE /api/user/{id})
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_prevents_non_admins_from_deleting_users(): void
    {
        $user1 = User::factory()->create(['role' => 'user']);
        $user2 = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user1, 'api')
            ->deleteJson("/api/user/{$user2->id}");

        $response->assertStatus(403);
    }

    protected function setUp(): void
    {
        parent::setUp();
    }
}
