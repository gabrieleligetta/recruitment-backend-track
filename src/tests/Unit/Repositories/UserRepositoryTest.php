<?php

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected UserRepository $userRepository;


    #[Test]
    public function it_can_filter_users_by_exact_name(): void
    {
        // Create some users with different names
        User::factory()->create(['name' => 'Mario Rossi']);
        User::factory()->create(['name' => 'Luigi Bianchi']);
        User::factory()->create(['name' => 'Carla Verdi']);

        // Define filter parameters
        $params = [
            'filters' => [
                [
                    'field' => 'name',
                    'value' => 'Mario Rossi',
                    'fieldType' => 'text',
                    'operator' => 'equals',
                ],
            ],
        ];

        // Retrieve paginated results
        $results = $this->userRepository->all($params);

        // Assert that only one user is returned (Mario Rossi)
        $this->assertCount(1, $results);
        $this->assertEquals('Mario Rossi', $results->getCollection()->first()->name);
    }

    /*
    |--------------------------------------------------------------------------
    | FILTER (LISTING) TESTS
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_can_filter_users_by_partial_name(): void
    {
        // Create users whose names share a common substring
        User::factory()->create(['name' => 'Mario Rossi']);
        User::factory()->create(['name' => 'Mariano De Maria']);
        User::factory()->create(['name' => 'Luigi Bianchi']);

        // Define filter parameters for a "contains" operator
        $params = [
            'filters' => [
                [
                    'field' => 'name',
                    'value' => 'Mari',
                    'fieldType' => 'text',
                    'operator' => 'contains',
                ],
            ],
        ];

        $results = $this->userRepository->all($params);

        // Check that 2 results are returned
        $this->assertCount(2, $results);
        $names = $results->getCollection()->pluck('name');
        $this->assertTrue($names->contains('Mario Rossi'));
        $this->assertTrue($names->contains('Mariano De Maria'));
    }

    #[Test]
    public function it_can_filter_users_by_email_starts_with(): void
    {
        // Create users with various emails
        User::factory()->create(['email' => 'example@test.com']);
        User::factory()->create(['email' => 'exa123@test.com']);
        User::factory()->create(['email' => 'admin@mydomain.com']);

        // Filter parameter: operator 'startsWith' with value 'exa'
        $params = [
            'filters' => [
                [
                    'field' => 'email',
                    'value' => 'exa',
                    'fieldType' => 'text',
                    'operator' => 'startsWith',
                ],
            ],
        ];

        $results = $this->userRepository->all($params);

        // Assert that 2 results match (example@test.com, exa123@test.com)
        $this->assertCount(2, $results);
        $emails = $results->getCollection()->pluck('email');
        $this->assertTrue($emails->contains('example@test.com'));
        $this->assertTrue($emails->contains('exa123@test.com'));
    }

    #[Test]
    public function it_can_filter_users_by_email_not_blank(): void
    {
        // If email is NOT NULL in the database, '' counts as blank.
        // We create one user with an empty string and another with a real email.
        User::factory()->create(['email' => '']);
        User::factory()->create(['email' => 'nonempty@test.com']);

        // 'notBlank' operator should exclude the empty-string record.
        $params = [
            'filters' => [
                [
                    'field' => 'email',
                    'fieldType' => 'text',
                    'operator' => 'notBlank',
                ],
            ],
        ];

        $results = $this->userRepository->all($params);

        // We expect only 1 result
        $this->assertCount(1, $results);
        $this->assertEquals('nonempty@test.com', $results->getCollection()->first()->email);
    }

    #[Test]
    public function it_can_filter_users_by_numeric_id_range(): void
    {
        // Create three users (the ID field will auto-increment, but let's not assume it starts at 1)
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $thirdUser  = User::factory()->create();

        // Retrieve the actual IDs of the second and third user
        $id2 = $secondUser->id;
        $id3 = $thirdUser->id;

        $params = [
            'filters' => [
                [
                    'field'      => 'id',
                    'value'      => $id2,
                    'rangeValue' => $id3,
                    'fieldType'  => 'number',
                    'operator'   => 'inRange',
                ],
            ],
        ];
        $results = $this->userRepository->all($params);

        // We expect exactly 2 records in the range [id2, id3]
        $this->assertCount(2, $results);
        $ids = $results->getCollection()->pluck('id');
        $this->assertTrue($ids->contains($id2));
        $this->assertTrue($ids->contains($id3));
    }

    #[Test]
    public function it_can_create_a_new_user(): void
    {
        // Prepare the user data
        $data = [
            'name'     => 'Test User',
            'email'    => 'testuser@example.com',
            'password' => bcrypt('secret'),
        ];

        // Create the user via the repository (make sure the create() method actually saves the user)
        $newUser = $this->userRepository->create($data);

        // Assert that the user was created and saved properly
        $this->assertNotNull($newUser->id);
        $this->assertEquals('Test User', $newUser->name);
        $this->assertEquals('testuser@example.com', $newUser->email);
        $this->assertTrue(Hash::check('secret', $newUser->password));
    }

    /*
    |--------------------------------------------------------------------------
    | CRUD METHOD TESTS
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_can_find_user_by_id(): void
    {
        // Create a user
        $user = User::factory()->create();

        // Try to find it by ID (make sure findById($id) is defined in the repository)
        $foundUser = $this->userRepository->findById($user->id);

        // Confirm we can retrieve the correct user
        $this->assertNotNull($foundUser);
        $this->assertEquals($user->id, $foundUser->id);
    }

    #[Test]
    public function it_returns_null_when_user_not_found_by_id(): void
    {
        // Attempt to find a user with a non-existent ID
        $foundUser = $this->userRepository->findById(999999);

        // We expect null in this scenario
        $this->assertNull($foundUser);
    }

    #[Test]
    public function it_can_update_an_existing_user(): void
    {
        // Create a user with an old name
        $user = User::factory()->create(['name' => 'Old Name']);

        // We'll update the name only
        $updateData = [
            'name' => 'New Name',
        ];

        // Make sure the repository's update($id, $data) performs the save
        $updatedUser = $this->userRepository->update($user->id, $updateData);

        // Assert that the record is updated
        $this->assertEquals('New Name', $updatedUser->name);
        $this->assertEquals($user->id, $updatedUser->id);
    }

    #[Test]
    public function it_can_delete_a_user(): void
    {
        // Create a user
        $user = User::factory()->create();

        // Delete through the repository
        $this->userRepository->delete($user->id);

        // Assert that the user's record is missing from the database
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    /**
     * This method runs before each test, setting up a fresh test environment
     * and initializing the UserRepository.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = new UserRepository();
    }
}
