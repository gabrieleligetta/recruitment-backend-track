<?php

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Models\TaxProfile;
use App\Repositories\TaxProfileRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaxProfileRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected TaxProfileRepository $taxProfileRepository;

    #[Test]
    public function it_can_filter_tax_profiles_by_exact_company_name(): void
    {
        // These records do not reference a user_id, or if the factory does,
        // ensure that the user is created within the factory or no foreign key is required.
        TaxProfile::factory()->create(['company_name' => 'Acme Inc.']);
        TaxProfile::factory()->create(['company_name' => 'Global Solutions']);
        TaxProfile::factory()->create(['company_name' => 'Vision Corp.']);

        // Define a filter to match "Acme Inc." exactly
        $params = [
            'filters' => [
                [
                    'field' => 'company_name',
                    'value' => 'Acme Inc.',
                    'fieldType' => 'text',
                    'operator' => 'equals',
                ],
            ],
        ];

        $results = $this->taxProfileRepository->all($params);

        $this->assertCount(1, $results);
        $this->assertEquals('Acme Inc.', $results->getCollection()->first()->company_name);
    }

    /*
    |--------------------------------------------------------------------------
    | FILTER (LISTING) TESTS
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_can_filter_tax_profiles_by_partial_address(): void
    {
        TaxProfile::factory()->create(['address' => '123 Main Street']);
        TaxProfile::factory()->create(['address' => '456 Elm Street']);
        TaxProfile::factory()->create(['address' => '789 Maple Avenue']);

        $params = [
            'filters' => [
                [
                    'field' => 'address',
                    'value' => 'Street',
                    'fieldType' => 'text',
                    'operator' => 'contains',
                ],
            ],
        ];

        $results = $this->taxProfileRepository->all($params);

        $this->assertCount(2, $results);
        $addresses = $results->getCollection()->pluck('address');
        $this->assertTrue($addresses->contains('123 Main Street'));
        $this->assertTrue($addresses->contains('456 Elm Street'));
    }

    #[Test]
    public function it_can_filter_tax_profiles_by_zip_code_not_blank(): void
    {
        // If zip_code is NOT NULL in the DB, treat '' as blank
        TaxProfile::factory()->create(['zip_code' => '']);      // blank
        TaxProfile::factory()->create(['zip_code' => '12345']); // not blank

        $params = [
            'filters' => [
                [
                    'field' => 'zip_code',
                    'fieldType' => 'text',
                    'operator' => 'notBlank',
                ],
            ],
        ];

        $results = $this->taxProfileRepository->all($params);

        // Expect only 1 record (the one with '12345')
        $this->assertCount(1, $results);
        $this->assertEquals('12345', $results->getCollection()->first()->zip_code);
    }

    #[Test]
    public function it_can_filter_tax_profiles_by_numeric_user_id_range(): void
    {
        // Create 3 different users so we have valid foreign keys
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        // Create TaxProfiles referencing those user IDs
        TaxProfile::factory()->create(['user_id' => $user1->id]);
        TaxProfile::factory()->create(['user_id' => $user2->id]);
        TaxProfile::factory()->create(['user_id' => $user3->id]);

        // Filter for user_id in range [user2->id, user3->id]
        $params = [
            'filters' => [
                [
                    'field'       => 'user_id',
                    'value'       => $user2->id,
                    'rangeValue'  => $user3->id,
                    'fieldType'   => 'number',
                    'operator'    => 'inRange',
                ],
            ],
        ];

        $results = $this->taxProfileRepository->all($params);

        $this->assertCount(2, $results);
        $ids = $results->getCollection()->pluck('user_id');
        $this->assertTrue($ids->contains($user2->id));
        $this->assertTrue($ids->contains($user3->id));
    }

    #[Test]
    public function it_can_create_a_new_tax_profile(): void
    {
        // Create a user so user_id references a valid record
        $user = User::factory()->create();

        // Prepare data
        $data = [
            'user_id'      => $user->id, // must exist in 'users' table
            'tax_id'       => 'TAX-123',
            'company_name' => 'New Company',
            'address'      => '321 Pine Street',
            'country'      => 'Italy',
            'city'         => 'Rome',
            'zip_code'     => '00100',
        ];

        $taxProfile = $this->taxProfileRepository->create($data);

        $this->assertNotNull($taxProfile->id);
        $this->assertEquals($user->id, $taxProfile->user_id);
        $this->assertEquals('TAX-123', $taxProfile->tax_id);
        $this->assertEquals('New Company', $taxProfile->company_name);
        $this->assertEquals('00100', $taxProfile->zip_code);
    }

    /*
    |--------------------------------------------------------------------------
    | CRUD TESTS
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_can_find_tax_profile_by_id(): void
    {
        // Create user + corresponding tax profile
        $user = User::factory()->create();
        $profile = TaxProfile::factory()->create([
            'user_id' => $user->id,
            'company_name' => 'FindMe Inc.',
        ]);

        // Act: retrieve by ID
        $foundProfile = $this->taxProfileRepository->findById($profile->id);

        // Assert
        $this->assertNotNull($foundProfile);
        $this->assertEquals('FindMe Inc.', $foundProfile->company_name);
    }

    #[Test]
    public function it_returns_null_when_tax_profile_not_found_by_id(): void
    {
        $notFoundProfile = $this->taxProfileRepository->findById(99999999);
        $this->assertNull($notFoundProfile);
    }

    #[Test]
    public function it_can_update_an_existing_tax_profile(): void
    {
        // Create user + corresponding tax profile
        $user = User::factory()->create();
        $profile = TaxProfile::factory()->create([
            'user_id' => $user->id,
            'address' => 'Old Address',
        ]);

        $updateData = [
            'address' => 'New Address',
        ];

        $updatedProfile = $this->taxProfileRepository->update($profile->id, $updateData);

        $this->assertEquals('New Address', $updatedProfile->address);
        $this->assertEquals($profile->id, $updatedProfile->id);
    }

    #[Test]
    public function it_can_delete_a_tax_profile(): void
    {
        // Create user + tax profile
        $user = User::factory()->create();
        $profile = TaxProfile::factory()->create(['user_id' => $user->id]);

        $this->taxProfileRepository->delete($profile->id);

        // If it's a hard delete, it won't appear in DB
        $this->assertDatabaseMissing('tax_profiles', ['id' => $profile->id]);
    }

    /**
     * This method is called before each test, setting up
     * a fresh test environment and initializing the repository.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->taxProfileRepository = new TaxProfileRepository();
    }
}
