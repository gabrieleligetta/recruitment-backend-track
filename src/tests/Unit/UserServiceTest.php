<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\UserService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    protected UserService $userService;
    protected $mockUserRepository;

    #[Test]
    public function it_returns_a_paginated_list_of_users(): void
    {
        //Create a fake user
        $user = User::factory()->create();

        //Create a fake paginator with mock data
        $fakePaginator = new Paginator(new Collection([]), 0, 10);

        //Mock repository behavior
        $this->mockUserRepository
            ->shouldReceive('all')
            ->once()
            ->andReturn($fakePaginator);

        //Call the service method
        $result = $this->userService->getAll($user, []);

        //Assertions
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    /*
    |--------------------------------------------------------------------------
    |Test: Get All Users (getAll)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_finds_a_user_by_id(): void
    {
        $user = User::factory()->make(['id' => 1]);

        $this->mockUserRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($user);

        $result = $this->userService->getById(1);

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals(1, $result->id);
    }

    /*
    |--------------------------------------------------------------------------
    |Test: Find User By ID (getById)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_returns_null_when_user_not_found(): void
    {
        $this->mockUserRepository
            ->shouldReceive('findById')
            ->with(999)
            ->once()
            ->andReturn(null);

        $result = $this->userService->getById(999);

        $this->assertNull($result);
    }

    #[Test]
    public function it_creates_a_new_user(): void
    {
        $userData = [
            'name'     => 'John Doe',
            'email'    => 'john@example.com',
            'password' => 'hashedpassword',
        ];

        $user = User::factory()->make($userData);

        $this->mockUserRepository
            ->shouldReceive('create')
            ->with($userData)
            ->once()
            ->andReturn($user);

        $result = $this->userService->create($userData);

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('John Doe', $result->name);
    }

    /*
    |--------------------------------------------------------------------------
    |Test: Create User (create)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_updates_an_existing_user(): void
    {
        $user = User::factory()->make(['id' => 1, 'name' => 'John Doe']);

        $updatedData = ['name' => 'John Updated'];

        $this->mockUserRepository
            ->shouldReceive('update')
            ->with(1, $updatedData)
            ->once()
            ->andReturn($user);

        $result = $this->userService->update(1, $updatedData);

        $this->assertInstanceOf(User::class, $result);
    }

    #[Test]
    public function it_throws_validation_exception_for_extra_fields(): void
    {
        // 1) Prepare valid data
        $validData = [
            'name'     => 'Jane Doe',
            'email'    => 'jane@example.com',
            'password' => 'securepassword', // In your actual code, the create method also expects a 6+ char password
        ];

        // 2) Add an extra field 'foo' that isn't in the validation rules
        $invalidData = $validData + ['foo' => 'bar'];

        // 3) We expect a ValidationException from the service's validation
        $this->expectException(ValidationException::class);
        // The default top-level message is "The given data was invalid."
        $this->expectExceptionMessage('The given data was invalid.');

        // 4) Because validation fails, the repository create() method should NOT be called
        $this->mockUserRepository->shouldNotReceive('create');

        // 5) This call should trigger the validation exception
        $this->userService->create($invalidData);
    }


    /*
    |--------------------------------------------------------------------------
    |Test: Update User (update)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_returns_null_when_updating_non_existent_user(): void
    {
        $this->mockUserRepository
            ->shouldReceive('update')
            ->with(999, Mockery::any())
            ->once()
            ->andReturn(null);

        $result = $this->userService->update(999, ['name' => 'John Updated']);

        $this->assertNull($result);
    }

    #[Test]
    public function it_deletes_a_user(): void
    {
        $this->mockUserRepository
            ->shouldReceive('delete')
            ->with(1)
            ->once()
            ->andReturn(true);

        $result = $this->userService->delete(1);

        $this->assertTrue($result);
    }

    /*
    |--------------------------------------------------------------------------
    |Test: Delete User (delete)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_returns_false_when_deleting_non_existent_user(): void
    {
        $this->mockUserRepository
            ->shouldReceive('delete')
            ->with(999)
            ->once()
            ->andReturn(false);

        $result = $this->userService->delete(999);

        $this->assertFalse($result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        //Mock UserRepository
        $this->mockUserRepository = Mockery::mock(UserRepository::class);

        //Inject the mocked repository into UserService
        $this->userService = new UserService($this->mockUserRepository);
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
