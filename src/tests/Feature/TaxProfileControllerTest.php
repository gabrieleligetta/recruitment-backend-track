<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\TaxProfile;
use App\Services\TaxProfileService;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaxProfileControllerTest extends TestCase
{
    use RefreshDatabase;


    /*
    |--------------------------------------------------------------------------
    | TEST: List Tax Profiles (POST /api/tax-profile/list)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_can_paginate_and_sort_tax_profiles_by_company_name(): void
    {
        // 1) Create an admin (who can see all profiles).
        $admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'ZZZ Admin', // optional, just a clear name
        ]);

        // 2) Create multiple TaxProfiles with predictable 'company_name' values.
        //    We'll make 15 total so that, with limit=5, we have exactly 3 pages.
        $companyNames = [
            'Acme-001', 'Acme-002', 'Acme-003', 'Acme-004', 'Acme-005',
            'Acme-006', 'Acme-007', 'Acme-008', 'Acme-009', 'Acme-010',
            'Acme-011', 'Acme-012', 'Acme-013', 'Acme-014', 'Acme-015',
        ];

        foreach ($companyNames as $name) {
            TaxProfile::factory()->create([
                'user_id'      => $admin->id,
                'company_name' => $name,
            ]);
        }

        // 3) PAGE 1: Request the first 5 profiles, sorted ascending by 'company_name'
        $responsePage1 = $this->actingAs($admin, 'api')
            ->postJson('/api/tax-profile/list', [
                'filters' => [],
                'sort'    => ['field' => 'company_name', 'direction' => 'asc'],
                'limit'   => 5,
                'page'    => 1,
            ]);

        $responsePage1->assertStatus(200);
        $responsePage1->assertJsonStructure([
            'data' => [['id', 'company_name', 'country']], // Adjust fields as needed
            'current_page',
            'total',
            'per_page',
            'last_page',
        ]);

        $page1Data = $responsePage1->json('data');
        $this->assertCount(5, $page1Data, 'Page 1 should contain 5 profiles');
        $this->assertEquals(1, $responsePage1->json('current_page'));
        $this->assertEquals(15, $responsePage1->json('total'));
        $this->assertEquals(5, $responsePage1->json('per_page'));
        $this->assertEquals(3, $responsePage1->json('last_page')); // 15 total => 3 pages at 5 per page

        // Verify the first 5 companies match the expected order
        $actualNamesPage1 = array_column($page1Data, 'company_name');
        $this->assertEquals(
            ['Acme-001', 'Acme-002', 'Acme-003', 'Acme-004', 'Acme-005'],
            $actualNamesPage1,
            'Page 1 company names are not in the expected order'
        );

        // 4) PAGE 2
        $responsePage2 = $this->actingAs($admin, 'api')
            ->postJson('/api/tax-profile/list', [
                'filters' => [],
                'sort'    => ['field' => 'company_name', 'direction' => 'asc'],
                'limit'   => 5,
                'page'    => 2,
            ]);

        $responsePage2->assertStatus(200);
        $page2Data = $responsePage2->json('data');
        $this->assertCount(5, $page2Data, 'Page 2 should contain 5 profiles');

        $actualNamesPage2 = array_column($page2Data, 'company_name');
        $this->assertEquals(
            ['Acme-006', 'Acme-007', 'Acme-008', 'Acme-009', 'Acme-010'],
            $actualNamesPage2,
            'Page 2 company names are not in the expected order'
        );

        // 5) PAGE 3 (final page)
        $responsePage3 = $this->actingAs($admin, 'api')
            ->postJson('/api/tax-profile/list', [
                'filters' => [],
                'sort'    => ['field' => 'company_name', 'direction' => 'asc'],
                'limit'   => 5,
                'page'    => 3,
            ]);

        $responsePage3->assertStatus(200);
        $page3Data = $responsePage3->json('data');
        $this->assertCount(5, $page3Data, 'Page 3 should contain the remaining 5 profiles');

        $actualNamesPage3 = array_column($page3Data, 'company_name');
        $this->assertEquals(
            ['Acme-011', 'Acme-012', 'Acme-013', 'Acme-014', 'Acme-015'],
            $actualNamesPage3,
            'Page 3 company names are not in the expected order'
        );

        // Confirm final pagination info
        $this->assertEquals(3, $responsePage3->json('current_page'));
        $this->assertEquals(15, $responsePage3->json('total'));
        $this->assertEquals(5, $responsePage3->json('per_page'));
        $this->assertEquals(3, $responsePage3->json('last_page'));
    }


    #[Test]
    public function it_correctly_lists_tax_profiles_based_on_user_role(): void
    {
        //Create an admin and two regular users
        $admin = User::factory()->create(['role' => 'admin']);
        $user1 = User::factory()->create(['role' => 'user']);
        $user2 = User::factory()->create(['role' => 'user']);

        //Ensure database is fresh before inserting
        TaxProfile::query()->delete();

        //Create tax profiles for both users
        TaxProfile::factory()->count(2)->create(['user_id' => $user1->id]);
        TaxProfile::factory()->count(3)->create(['user_id' => $user2->id]);

        //Admin should see all tax profiles (2 + 3 = 5)
        $adminResponse = $this->actingAs($admin, 'api')
            ->postJson('/api/tax-profile/list', []);

        $adminResponse->assertStatus(200);
        $this->assertEquals(5, count($adminResponse->json('data'))); //Ensure correct count

        //User1 should only see their own tax profiles (2)
        $user1Response = $this->actingAs($user1, 'api')
            ->postJson('/api/tax-profile/list', []);

        $user1Response->assertStatus(200);
        $this->assertEquals(2, count($user1Response->json('data')));

        //User2 should only see their own tax profiles (3)
        $user2Response = $this->actingAs($user2, 'api')
            ->postJson('/api/tax-profile/list', []);

        $user2Response->assertStatus(200);
        $this->assertEquals(3, count($user2Response->json('data')));
    }

    #[Test]
    public function it_returns_server_error_on_list_exception(): void
    {
        $user = User::factory()->create();

        // Force the service to throw an exception when getAll is called
        $this->partialMock(TaxProfileService::class, function ($mock) {
            $mock->shouldReceive('getAll')->andThrow(new Exception("Simulated exception"));
        });

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/tax-profile/list', []);

        $response->assertStatus(500);
        $this->assertEquals('Server Error', $response->json('message'));
    }



    /*
    |--------------------------------------------------------------------------
    | TEST: Show Tax Profile (GET /api/tax-profile/{id})
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_can_show_a_tax_profile(): void
    {
        $user = User::factory()->create();
        $profile = TaxProfile::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')
            ->getJson("/api/tax-profile/{$profile->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'id'           => $profile->id,
            'company_name' => $profile->company_name,
        ]);
    }

    #[Test]
    public function it_denies_access_to_non_owner_users(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $profile = TaxProfile::factory()->create(['user_id' => $user1->id]);

        $response = $this->actingAs($user2, 'api')
            ->getJson("/api/tax-profile/{$profile->id}");

        $response->assertStatus(403); //Expect forbidden
    }

    #[Test]
    public function it_returns_404_when_tax_profile_not_found_in_show(): void
    {
        $user = User::factory()->create();

        // Use an ID that doesn’t exist
        $nonExistingId = 9999;

        $response = $this->actingAs($user, 'api')
            ->getJson("/api/tax-profile/{$nonExistingId}");

        $response->assertStatus(404);
        $this->assertEquals('Tax Profile not found', $response->json('message'));
    }

    #[Test]
    public function it_returns_server_error_on_show_exception(): void
    {
        $user = User::factory()->create();

        // Force the service to throw an exception when getById is called
        $this->partialMock(TaxProfileService::class, function ($mock) {
            $mock->shouldReceive('getById')->andThrow(new Exception("Simulated exception"));
        });

        // Use any ID, as the exception will be thrown before lookup
        $response = $this->actingAs($user, 'api')
            ->getJson("/api/tax-profile/1");

        $response->assertStatus(500);
        $this->assertEquals('Server Error', $response->json('message'));
    }



    /*
    |--------------------------------------------------------------------------
    | TEST: Create Tax Profile (POST /api/tax-profile)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_allows_users_to_create_their_own_tax_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/tax-profile', [
                'tax_id'       => 'TX-1234',
                'company_name' => 'New Company',
                'address'      => '123 Test St',
                'country'      => 'USA',
                'city'         => 'New York',
                'zip_code'     => '10001',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('tax_profiles', ['company_name' => 'New Company']);
    }

    #[Test]
    public function it_allows_admins_to_create_a_tax_profile_for_any_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/tax-profile', [
                'user_id'      => $user->id,
                'tax_id'       => 'TX-5678',
                'company_name' => 'Admin Created Co',
                'address'      => '456 Admin St',
                'country'      => 'UK',
                'city'         => 'London',
                'zip_code'     => 'E1 6AN',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('tax_profiles', ['company_name' => 'Admin Created Co']);
    }

    #[Test]
    public function it_returns_validation_errors_when_required_fields_are_missing_on_store(): void
    {
        $user = User::factory()->create();

        // Simulate a validation exception by forcing the service to throw one.
        $this->partialMock(TaxProfileService::class, function ($mock) {
            $validator = Validator::make([], ['tax_id' => 'required']);
            $mock->shouldReceive('create')
                ->andThrow(new ValidationException($validator));
        });

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/tax-profile', [
                // Missing the 'tax_id' field
                'company_name' => 'Test Company',
                'address'      => 'Test Address',
                'country'      => 'USA',
                'city'         => 'Test City',
                'zip_code'     => '12345'
            ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('tax_id', $response->json('message'));
    }

    #[Test]
    public function it_returns_forbidden_when_not_authorized_to_create_tax_profile(): void
    {
        $user = User::factory()->create();

        // Force the service to throw an authorization exception
        $this->partialMock(TaxProfileService::class, function ($mock) {
            $mock->shouldReceive('create')
                ->andThrow(new AuthorizationException("Forbidden"));
        });

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/tax-profile', [
                'tax_id'       => 'TX-999',
                'company_name' => 'Test Company',
                'address'      => 'Test Address',
                'country'      => 'USA',
                'city'         => 'Test City',
                'zip_code'     => '12345'
            ]);

        $response->assertStatus(403);
        $this->assertEquals('Forbidden', $response->json('message'));
    }

    #[Test]
    public function it_returns_server_error_on_store_exception(): void
    {
        $user = User::factory()->create();

        // Force the service to throw a generic exception when create is called
        $this->partialMock(TaxProfileService::class, function ($mock) {
            $mock->shouldReceive('create')
                ->andThrow(new Exception("Simulated exception"));
        });

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/tax-profile', [
                'tax_id'       => 'TX-123',
                'company_name' => 'Test Company',
                'address'      => 'Test Address',
                'country'      => 'USA',
                'city'         => 'Test City',
                'zip_code'     => '12345'
            ]);

        $response->assertStatus(500);
        $this->assertEquals('Server Error', $response->json('message'));
    }



    /*
    |--------------------------------------------------------------------------
    | TEST: Update Tax Profile (PUT /api/tax-profile/{id})
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_allows_admin_or_owner_to_update_tax_profile(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $profile = TaxProfile::factory()->create(['user_id' => $user->id]);

        //Admin updates another user's profile
        $responseAdmin = $this->actingAs($admin, 'api')
            ->putJson("/api/tax-profile/{$profile->id}", [
                'user_id'      => $profile->user_id, //Ensure user_id is sent
                'company_name' => 'Updated Admin Co'
            ]);

        $responseAdmin->assertStatus(200);

        //User updates their own profile
        $responseUser = $this->actingAs($user, 'api')
            ->putJson("/api/tax-profile/{$profile->id}", [
                'user_id'      => $profile->user_id, //Ensure user_id is sent
                'company_name' => 'User Updated Co'
            ]);

        $responseUser->assertStatus(200);
    }

    #[Test]
    public function it_prevents_non_owner_users_from_updating_profiles(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $profile = TaxProfile::factory()->create(['user_id' => $user1->id]);

        $response = $this->actingAs($user2, 'api')
            ->putJson("/api/tax-profile/{$profile->id}", ['company_name' => 'Unauthorized Update']);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_returns_404_when_tax_profile_not_found_on_update(): void
    {
        $user = User::factory()->create();

        // Force the update method to return null to simulate not found
        $this->partialMock(TaxProfileService::class, function ($mock) {
            $mock->shouldReceive('update')->andReturn(null);
        });

        $response = $this->actingAs($user, 'api')
            ->putJson('/api/tax-profile/9999', ['company_name' => 'Updated Name']);

        $response->assertStatus(404);
        $this->assertEquals('Tax Profile not found', $response->json('message'));
    }

    #[Test]
    public function it_returns_server_error_on_update_exception(): void
    {
        $user = User::factory()->create();
        $profile = TaxProfile::factory()->create(['user_id' => $user->id]);

        $this->partialMock(TaxProfileService::class, function ($mock) use ($profile) {
            $mock->shouldReceive('update')
                ->with(Mockery::any(), $profile->id, Mockery::any())
                ->andThrow(new Exception("Simulated exception"));
        });

        $response = $this->actingAs($user, 'api')
            ->putJson("/api/tax-profile/{$profile->id}", ['company_name' => 'Updated Name']);

        $response->assertStatus(500);
        $this->assertEquals('Server Error', $response->json('message'));
    }

    #[Test]
    public function it_returns_forbidden_on_update_forbidden_exception(): void
    {
        $user = User::factory()->create();
        $profile = TaxProfile::factory()->create(['user_id' => $user->id]);

        $this->partialMock(TaxProfileService::class, function ($mock) use ($profile) {
            $mock->shouldReceive('update')
                ->with(Mockery::any(), $profile->id, Mockery::any())
                ->andThrow(new AuthorizationException("Forbidden"));
        });

        $response = $this->actingAs($user, 'api')
            ->putJson("/api/tax-profile/{$profile->id}", ['company_name' => 'Updated Name']);

        $response->assertStatus(403);
        $this->assertEquals('Forbidden', $response->json('message'));
    }



    /*
    |--------------------------------------------------------------------------
    | TEST: Delete Tax Profile (DELETE /api/tax-profile/{id})
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_allows_admin_or_owner_to_delete_a_tax_profile(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $profile = TaxProfile::factory()->create(['user_id' => $user->id]);

        // Admin deletes another user's profile
        $responseAdmin = $this->actingAs($admin, 'api')
            ->deleteJson("/api/tax-profile/{$profile->id}");
        $responseAdmin->assertStatus(200);

        // User deletes their own profile
        $profile2 = TaxProfile::factory()->create(['user_id' => $user->id]);
        $responseUser = $this->actingAs($user, 'api')
            ->deleteJson("/api/tax-profile/{$profile2->id}");
        $responseUser->assertStatus(200);
    }

    #[Test]
    public function it_prevents_non_owner_users_from_deleting_profiles(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $profile = TaxProfile::factory()->create(['user_id' => $user1->id]);

        $response = $this->actingAs($user2, 'api')
            ->deleteJson("/api/tax-profile/{$profile->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function it_returns_404_when_tax_profile_not_found_on_destroy(): void
    {
        $user = User::factory()->create();

        // Force the delete method to return false to simulate not found
        $this->partialMock(TaxProfileService::class, function ($mock) {
            $mock->shouldReceive('delete')->andReturn(false);
        });

        $response = $this->actingAs($user, 'api')
            ->deleteJson('/api/tax-profile/9999');

        $response->assertStatus(404);
        $this->assertEquals('Tax Profile not found', $response->json('message'));
    }

    #[Test]
    public function it_returns_server_error_on_destroy_exception(): void
    {
        $user = User::factory()->create();
        $profile = TaxProfile::factory()->create(['user_id' => $user->id]);

        $this->partialMock(TaxProfileService::class, function ($mock) use ($profile) {
            $mock->shouldReceive('delete')
                ->with(Mockery::any(), $profile->id)
                ->andThrow(new Exception("Simulated exception"));
        });

        $response = $this->actingAs($user, 'api')
            ->deleteJson("/api/tax-profile/{$profile->id}");

        $response->assertStatus(500);
        $this->assertEquals('Server Error', $response->json('message'));
    }

    #[Test]
    public function it_returns_forbidden_on_destroy_forbidden_exception(): void
    {
        $user = User::factory()->create();
        $profile = TaxProfile::factory()->create(['user_id' => $user->id]);

        $this->partialMock(TaxProfileService::class, function ($mock) use ($profile) {
            $mock->shouldReceive('delete')
                ->with(Mockery::any(), $profile->id)
                ->andThrow(new AuthorizationException("Forbidden"));
        });

        $response = $this->actingAs($user, 'api')
            ->deleteJson("/api/tax-profile/{$profile->id}");

        $response->assertStatus(403);
        $this->assertEquals('Forbidden', $response->json('message'));
    }


}
