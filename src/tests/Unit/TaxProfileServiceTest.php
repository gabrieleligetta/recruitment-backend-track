<?php

namespace Tests\Unit\Services;

use App\Models\TaxProfile;
use App\Models\User;
use App\Repositories\TaxProfileRepository;
use App\Services\TaxProfileService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaxProfileServiceTest extends TestCase
{
    protected TaxProfileService $taxProfileService;
    protected $mockTaxProfileRepository;

    #[Test]
    public function it_returns_a_paginated_list_of_tax_profiles(): void
    {
        $user = User::factory()->make();

        $fakePaginator = new Paginator(new Collection([]), 0, 10);

        $this->mockTaxProfileRepository
            ->shouldReceive('all')
            ->once()
            ->andReturn($fakePaginator);

        $result = $this->taxProfileService->getAll($user, []);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    /*
    |--------------------------------------------------------------------------
    |Test: Get All Tax Profiles (getAll)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_finds_a_tax_profile_by_id(): void
    {
        $user = User::factory()->make();
        $taxProfile = TaxProfile::factory()->make(['id' => 1, 'user_id' => $user->id]);

        $this->mockTaxProfileRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($taxProfile);

        $result = $this->taxProfileService->getById($user,1);

        $this->assertInstanceOf(TaxProfile::class, $result);
        $this->assertEquals(1, $result->id);
    }

    /*
    |--------------------------------------------------------------------------
    |Test: Find Tax Profile By ID (getById)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_returns_null_when_tax_profile_not_found(): void
    {
        $user = User::factory()->make();
        $this->mockTaxProfileRepository
            ->shouldReceive('findById')
            ->with(999)
            ->once()
            ->andReturn(null);

        $result = $this->taxProfileService->getById($user,999);

        $this->assertNull($result);
    }

    #[Test]
    public function it_creates_a_new_tax_profile(): void
    {
        $authUser = User::factory()->create(['id' => 1, 'role' => 'admin']);

        $taxProfileData = [
            'user_id'      => 1,
            'tax_id'       => 'TAX-1234',
            'company_name' => 'Example Corp',
            'address'      => '123 Business St',
            'country'      => 'USA',
            'city'         => 'New York',
            'zip_code'     => '10001',
        ];

        $taxProfile = TaxProfile::factory()->make($taxProfileData);

        $this->mockTaxProfileRepository
            ->shouldReceive('create')
            ->with(Mockery::on(function ($arg) use ($taxProfileData) {
                return array_intersect_assoc($arg, $taxProfileData) === $taxProfileData;
            })) //Matches only expected fields, ignoring any automatic changes
            ->once()
            ->andReturn($taxProfile);

        $result = $this->taxProfileService->create($authUser, $taxProfileData);

        $this->assertInstanceOf(TaxProfile::class, $result);
        $this->assertEquals('Example Corp', $result->company_name);
    }


    /*
    |--------------------------------------------------------------------------
    |Test: Create Tax Profile (create)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_updates_an_existing_tax_profile(): void
    {
        $authUser = User::factory()->create(); //Ensure user ID is generated uniquely

        $updatedData = [
            'user_id'      => $authUser->id, //Ensure valid user ID
            'company_name' => 'Updated Corp',
        ];
        $taxProfile = TaxProfile::factory()->create(['user_id' => $authUser->id, 'company_name' => 'Old Corp']);

        $this->mockTaxProfileRepository
            ->shouldReceive('findById')
            ->with($taxProfile->id)
            ->once()
            ->andReturn($taxProfile);

        $updatedTaxProfile = clone $taxProfile;
        $updatedTaxProfile->company_name = 'Updated Corp';

        $this->mockTaxProfileRepository
            ->shouldReceive('update')
            ->with($taxProfile->id, Mockery::subset($updatedData))
            ->once()
            ->andReturn($updatedTaxProfile);

        $result = $this->taxProfileService->update($authUser, $taxProfile->id, $updatedData);

        $this->assertInstanceOf(TaxProfile::class, $result);
        $this->assertEquals('Updated Corp', $result->company_name);
    }



    /*
    |--------------------------------------------------------------------------
    |Test: Update Tax Profile (update)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_returns_null_when_updating_non_existent_tax_profile(): void
    {
        $authUser = User::factory()->make(['id' => 1, 'role' => 'admin']);

        $this->mockTaxProfileRepository
            ->shouldReceive('findById')
            ->with(999)
            ->once()
            ->andReturn(null);

        $result = $this->taxProfileService->update($authUser, 999, ['company_name' => 'Updated Corp']);

        $this->assertNull($result);
    }

    #[Test]
    public function it_deletes_a_tax_profile(): void
    {
        $regularUser = User::factory()->create(['role' => 'user']);
        $taxProfile = TaxProfile::factory()->make(['id' => 1, 'user_id' => $regularUser->id]);

        //Mock `findById()` first
        $this->mockTaxProfileRepository
            ->shouldReceive('findById')
            ->with($taxProfile->id)
            ->once()
            ->andReturn($taxProfile);

        //Then mock `delete()`
        $this->mockTaxProfileRepository
            ->shouldReceive('delete')
            ->with($taxProfile->id)
            ->once()
            ->andReturn(true);

        $result = $this->taxProfileService->delete($regularUser, $taxProfile->id);

        $this->assertTrue($result);
    }


    /*
    |--------------------------------------------------------------------------
    |Test: Delete Tax Profile (delete)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_returns_false_when_deleting_non_existent_tax_profile(): void
    {
        $regularUser = User::factory()->create(['role' => 'user']);

        //Mock `findById()` to return `null`
        $this->mockTaxProfileRepository
            ->shouldReceive('findById')
            ->with(999)
            ->once()
            ->andReturn(null);

        //Mock `delete()` should not be called if the profile is not found
        $this->mockTaxProfileRepository
            ->shouldReceive('delete')
            ->never();

        $result = $this->taxProfileService->delete($regularUser, 999);

        $this->assertFalse($result);
    }

    public function it_allows_admin_to_create_a_tax_profile_for_any_user(): void
    {
        $adminUser = User::factory()->create(['role' => 'admin']);
        $otherUser = User::factory()->create();

        $taxProfileData = [
            'user_id'      => $otherUser->id,
            'tax_id'       => 'TAX-5678',
            'company_name' => 'Admin Created Co',
            'address'      => '789 Business St',
            'country'      => 'UK',
            'city'         => 'London',
            'zip_code'     => 'E1 6AN',
        ];

        $taxProfile = TaxProfile::factory()->make($taxProfileData);

        $this->mockTaxProfileRepository
            ->shouldReceive('create')
            ->with(Mockery::subset($taxProfileData))
            ->once()
            ->andReturn($taxProfile);

        $result = $this->taxProfileService->create($adminUser, $taxProfileData);

        $this->assertInstanceOf(TaxProfile::class, $result);
        $this->assertEquals('Admin Created Co', $result->company_name);
    }

    #[Test]
    public function it_denies_users_from_updating_profiles_they_dont_own(): void
    {
        $regularUser = User::factory()->create(['role' => 'user']);
        $otherUser = User::factory()->create();

        $updatedData = ['company_name' => 'Unauthorized Update'];
        $taxProfile = TaxProfile::factory()->create(['user_id' => $otherUser->id]);

        $this->mockTaxProfileRepository
            ->shouldReceive('findById')
            ->with($taxProfile->id)
            ->once()
            ->andReturn($taxProfile);

        $this->expectException(AuthorizationException::class);

        $this->taxProfileService->update($regularUser, $taxProfile->id, $updatedData);
    }

    #[Test]
    public function it_denies_users_from_deleting_profiles_they_dont_own(): void
    {
        $regularUser = User::factory()->create(['role' => 'user']);
        $otherUser = User::factory()->create();

        $taxProfile = TaxProfile::factory()->create(['user_id' => $otherUser->id]);

        $this->mockTaxProfileRepository
            ->shouldReceive('findById')
            ->with($taxProfile->id)
            ->once()
            ->andReturn($taxProfile);

        $this->expectException(AuthorizationException::class);

        $this->taxProfileService->delete($regularUser, $taxProfile->id); //Pass the correct user
    }


    protected function setUp(): void
    {
        parent::setUp();

        //Mock TaxProfileRepository
        $this->mockTaxProfileRepository = Mockery::mock(TaxProfileRepository::class);

        //Inject the mocked repository into TaxProfileService
        $this->taxProfileService = new TaxProfileService($this->mockTaxProfileRepository);
    }

    /*
    |--------------------------------------------------------------------------
    |Cleanup Mockery
    |--------------------------------------------------------------------------
    */

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
