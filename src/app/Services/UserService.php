<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
        $filters = $this->prepareFilters($authUser, $requestData, false);
        return $this->userRepository->all($filters);
    }

    /**
     * Find a user by ID.
     */
    public function getById(int $id): ?User
    {
        return $this->userRepository->findById($id);
    }

    /**
     * Create a new user after validation.
     */
    public function create(array $data): User
    {
        $validatedData = $this->validateUser($data, false);
        return $this->userRepository->create($validatedData);
    }

    /**
     * Validate user input.
     */
    private function validateUser(array $data, bool $isUpdate = false, ?int $id = null): array
    {
        $rules = [
            'name'     => 'sometimes|required|string|max:255',
            'email'    => "sometimes|required|email|unique:users,email," . ($id ?? 'NULL'),
            'password' => $isUpdate ? 'sometimes|required|string|min:6' : 'required|string|min:6',
        ];

        return $this->generalValidation($data, $rules);
    }

    public function validateSignup(array $data): array
    {
        $rules = [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ];

        return $this->generalValidation($data, $rules);
    }

    /**
     * Update an existing user after validation.
     */
    public function update(int $id, array $data): ?User
    {
        $validatedData = $this->validateUser($data, true, $id);
        return $this->userRepository->update($id, $validatedData);
    }

    /**
     * Delete a user.
     */
    public function delete(int $id): bool
    {
        return $this->userRepository->delete($id);
    }
}
