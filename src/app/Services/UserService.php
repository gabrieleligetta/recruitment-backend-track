<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use App\Repositories\UserRepository;

class UserService
{
    protected UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function getAll(array $filters = [])
    : LengthAwarePaginator {
        return $this->userRepository->all($filters);
    }

    public function getById($id)
    : ?User {
        return $this->userRepository->findById($id);
    }

    public function create(array $data)
    : User {
        return $this->userRepository->create($data);
    }

    public function update($id, array $data)
    : ?User {
        return $this->userRepository->update($id, $data);
    }

    public function delete($id)
    : bool {
        return $this->userRepository->delete($id);
    }
}
