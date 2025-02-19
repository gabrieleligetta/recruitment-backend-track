<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\UserService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
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
