<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_paginate_and_sort_users_by_name(): void
    {
        // Create an admin user with a fixed name that sorts last in ascending order.
        $admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'ZZZ Admin',
        ]);

        // Create 15 users with distinct names.
        // These names will sort before 'ZZZ Admin' alphabetically.
        $names = [
            'Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo',
            'Foxtrot', 'Golf', 'Hotel', 'India', 'Juliet',
            'Kilo', 'Lima', 'Mike', 'November', 'Oscar',
        ];
        foreach ($names as $name) {
            User::factory()->create(['name' => $name]);
        }

        // PAGE 1: sort ascending by name, limit = 5
        $responsePage1 = $this->actingAs($admin, 'api')
            ->postJson('/api/user/list', [
                'filters' => ['name' => ''],
                'sort'    => ['field' => 'name', 'direction' => 'asc'],
                'limit'   => 5,
                'page'    => 1,
            ]);

        $responsePage1->assertStatus(200);
        $responsePage1->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'email']
            ],
            'current_page',
            'total',
            'per_page',
            'last_page',
        ]);

        // The first page should have 5 users
        $page1Data = $responsePage1->json('data');
        $this->assertCount(5, $page1Data, 'First page should contain 5 users');

        // Verify the first 5 are correctly sorted alphabetically
        $sortedNamesPage1 = array_column($page1Data, 'name');
        $this->assertEquals(
            ['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo'],
            $sortedNamesPage1
        );

        // Check pagination info after adding the admin (total = 16)
        $this->assertEquals(1, $responsePage1->json('current_page'), 'Current page should be 1');
        $this->assertEquals(16, $responsePage1->json('total'), 'Total items should be 16 (15 + 1 admin)');
        $this->assertEquals(5, $responsePage1->json('per_page'), 'Per page should be 5');
        $this->assertEquals(4, $responsePage1->json('last_page'), 'Last page should be 4 (16 / 5 = ~3.2 => 4)');

        // PAGE 2
        $responsePage2 = $this->actingAs($admin, 'api')
            ->postJson('/api/user/list', [
                'filters' => ['name' => ''],
                'sort'    => ['field' => 'name', 'direction' => 'asc'],
                'limit'   => 5,
                'page'    => 2,
            ]);

        $responsePage2->assertStatus(200);
        $page2Data = $responsePage2->json('data');
        $this->assertCount(5, $page2Data, 'Second page should contain 5 users');

        $sortedNamesPage2 = array_column($page2Data, 'name');
        // Next 5 in alphabetical order: Foxtrot, Golf, Hotel, India, Juliet
        $this->assertEquals(
            ['Foxtrot', 'Golf', 'Hotel', 'India', 'Juliet'],
            $sortedNamesPage2
        );

        // PAGE 3
        $responsePage3 = $this->actingAs($admin, 'api')
            ->postJson('/api/user/list', [
                'filters' => ['name' => ''],
                'sort'    => ['field' => 'name', 'direction' => 'asc'],
                'limit'   => 5,
                'page'    => 3,
            ]);

        $responsePage3->assertStatus(200);
        $page3Data = $responsePage3->json('data');
        $this->assertCount(5, $page3Data, 'Third page should contain 5 users');

        $sortedNamesPage3 = array_column($page3Data, 'name');
        // Next 5 in alphabetical order: Kilo, Lima, Mike, November, Oscar
        $this->assertEquals(
            ['Kilo', 'Lima', 'Mike', 'November', 'Oscar'],
            $sortedNamesPage3
        );

        // PAGE 4: should have only 1 user: "ZZZ Admin"
        $responsePage4 = $this->actingAs($admin, 'api')
            ->postJson('/api/user/list', [
                'filters' => ['name' => ''],
                'sort'    => ['field' => 'name', 'direction' => 'asc'],
                'limit'   => 5,
                'page'    => 4,
            ]);

        $responsePage4->assertStatus(200);
        $page4Data = $responsePage4->json('data');
        $this->assertCount(1, $page4Data, 'Fourth page should contain 1 user');

        $sortedNamesPage4 = array_column($page4Data, 'name');
        $this->assertEquals(['ZZZ Admin'], $sortedNamesPage4, 'The only user on page 4 should be ZZZ Admin');
    }


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
        // Drop all tables and re-run migrations for a fresh DB
        Artisan::call('migrate:fresh');
    }
}
