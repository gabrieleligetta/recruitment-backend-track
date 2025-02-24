<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UserService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | TEST: List Users (POST /api/user/list)
    |--------------------------------------------------------------------------
    */
    #[Test]
    public function it_can_paginate_and_sort_users_by_name_as_admin(): void
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
    public function it_can_paginate_and_sort_users_by_name_as_user(): void
    {
        // Create an admin user with a fixed name that sorts last in ascending order.
        $admin = User::factory()->create([
            'role' => 'user',
            'name' => 'ZZZ User',
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
        $this->assertEquals(['ZZZ User'], $sortedNamesPage4, 'The only user on page 4 should be ZZZ User');
    }

    #[Test]
    public function it_returns_server_error_on_user_list_exception(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Force the UserService::getAll method to throw an exception.
        $this->partialMock(UserService::class, function ($mock) {
            $mock->shouldReceive('getAll')->andThrow(new Exception("Simulated exception"));
        });

        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/user/list', []);

        $response->assertStatus(500);
        $this->assertEquals('Server Error', $response->json('message'));
    }


    #[Test]
    public function it_can_list_users_as_user(): void
    {
        // Create an admin user to authenticate
        $admin = User::factory()->create(['role' => 'user']);

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



    #[Test]
    public function it_can_list_users_as_admin(): void
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
    | TEST: List Users (GET /api/user/{id})
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_can_get_a_user_by_id_as_admin(): void
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

    #[Test]
    public function it_can_get_a_user_by_id_as_user(): void
    {
        $admin = User::factory()->create(['role' => 'user']);
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
    public function it_returns_server_error_on_user_show_exception(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Force the UserService::getById method to throw an exception.
        $this->partialMock(UserService::class, function ($mock) {
            $mock->shouldReceive('getById')->andThrow(new Exception("Simulated exception"));
        });

        $response = $this->actingAs($admin, 'api')
            ->getJson("/api/user/1");

        $response->assertStatus(500);
        $this->assertEquals('Server Error', $response->json('message'));
    }



    /*
    |--------------------------------------------------------------------------
    | TEST: Create User (POST /api/user)
    |--------------------------------------------------------------------------
    */

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
    public function it_returns_validation_errors_on_user_store_when_required_fields_missing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Simulate a validation exception by forcing the create method to throw one.
        $this->partialMock(UserService::class, function ($mock) {
            $validator = Validator::make([], [
                'name'  => 'required',
                'email' => 'required|email',
            ]);
            $mock->shouldReceive('create')
                ->andThrow(new ValidationException($validator));
        });

        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/user', [
                // Omitting 'name' and providing an invalid 'email'
                'email'    => 'not-an-email',
                'password' => 'password123'
            ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('name', $response->json('message'));
    }

    #[Test]
    public function it_returns_server_error_on_user_store_exception(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Force the UserService::create method to throw a generic exception.
        $this->partialMock(UserService::class, function ($mock) {
            $mock->shouldReceive('create')->andThrow(new Exception("Simulated exception"));
        });

        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/user', [
                'name'     => 'Test User',
                'email'    => 'test@example.com',
                'password' => 'password123'
            ]);

        $response->assertStatus(500);
        $this->assertEquals('Server Error', $response->json('message'));
    }




    /*
    |--------------------------------------------------------------------------
    | TEST: Update User (PUT /api/user/{id})
    |--------------------------------------------------------------------------
    */

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
    public function it_returns_404_when_user_not_found_on_update(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Simulate a "not found" condition by having the update method return null.
        $this->partialMock(UserService::class, function ($mock) {
            $mock->shouldReceive('update')->andReturn(null);
        });

        $response = $this->actingAs($admin, 'api')
            ->putJson('/api/user/9999', ['name' => 'Updated Name']);

        $response->assertStatus(404);
        $this->assertEquals('User not found', $response->json('message'));
    }

    #[Test]
    public function it_returns_server_error_on_user_update_exception(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $existingUser = User::factory()->create();

        // Force the UserService::update method to throw an exception.
        $this->partialMock(UserService::class, function ($mock) use ($existingUser) {
            $mock->shouldReceive('update')->andThrow(new Exception("Simulated exception"));
        });

        $response = $this->actingAs($admin, 'api')
            ->putJson("/api/user/{$existingUser->id}", ['name' => 'Updated Name']);

        $response->assertStatus(500);
        $this->assertEquals('Server Error', $response->json('message'));
    }



    /*
    |--------------------------------------------------------------------------
    | TEST: Delete User (DELETE /api/user/{id})
    |--------------------------------------------------------------------------
    */

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

    #[Test]
    public function it_prevents_non_admins_from_deleting_users(): void
    {
        $user1 = User::factory()->create(['role' => 'user']);
        $user2 = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user1, 'api')
            ->deleteJson("/api/user/{$user2->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function it_returns_404_when_user_not_found_on_delete(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Simulate a not found condition by having the delete method return false.
        $this->partialMock(UserService::class, function ($mock) {
            $mock->shouldReceive('delete')->andReturn(false);
        });

        $response = $this->actingAs($admin, 'api')
            ->deleteJson("/api/user/9999");

        $response->assertStatus(404);
        $this->assertEquals('User not found', $response->json('message'));
    }

    #[Test]
    public function it_returns_server_error_on_user_delete_exception(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $existingUser = User::factory()->create();

        // Force the UserService::delete method to throw an exception.
        $this->partialMock(UserService::class, function ($mock) use ($existingUser) {
            $mock->shouldReceive('delete')->andThrow(new Exception("Simulated exception"));
        });

        $response = $this->actingAs($admin, 'api')
            ->deleteJson("/api/user/{$existingUser->id}");

        $response->assertStatus(500);
        $this->assertEquals('Server Error', $response->json('message'));
    }


}
