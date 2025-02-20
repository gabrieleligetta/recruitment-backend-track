<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserService extends GeneralService
{
    protected UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Retrieve all users based on filters.
     */
    public function getAll(User $authUser, array $requestData = []): LengthAwarePaginator
    {
        try {
            $filters = $this->prepareFilters($authUser, $requestData, false);
            return $this->userRepository->all($filters);
        } catch (Throwable $e) {
            Log::error('Error fetching users', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Find a user by ID.
     */
    public function getById(int $id): ?User
    {
        try {
            return $this->userRepository->findById($id);
        } catch (Throwable $e) {
            Log::error('Error fetching user by ID', ['user_id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create a new user after validation.
     */
    public function create(array $data): User
    {
        try {
            $validatedData = $this->validateUser($data, false);
            return $this->userRepository->create($validatedData);
        } catch (Throwable $e) {
            Log::error('Error creating user', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Validate user input.
     */
    private function validateUser(array $data, bool $isUpdate = false, ?int $id = null): array
    {
        try {
            $rules = [
                'name'     => 'sometimes|required|string|max:255',
                'email'    => "sometimes|required|email|unique:users,email," . ($id ?? 'NULL'),
                'password' => $isUpdate ? 'sometimes|required|string|min:6' : 'required|string|min:6',
            ];

            return $this->generalValidation($data, $rules);
        } catch (Throwable $e) {
            Log::error('User validation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function validateSignup(array $data): array
    {
        try {
            $rules = [
                'name'     => 'required|string|max:255',
                'email'    => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:6',
            ];

            return $this->generalValidation($data, $rules);
        } catch (Throwable $e) {
            Log::error('Signup validation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Update an existing user after validation.
     */
    public function update(int $id, array $data): ?User
    {
        try {
            $validatedData = $this->validateUser($data, true, $id);
            return $this->userRepository->update($id, $validatedData);
        } catch (Throwable $e) {
            Log::error('Error updating user', ['user_id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Delete a user.
     */
    public function delete(int $id): bool
    {
        try {
            return $this->userRepository->delete($id);
        } catch (Throwable $e) {
            Log::error('Error deleting user', ['user_id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
