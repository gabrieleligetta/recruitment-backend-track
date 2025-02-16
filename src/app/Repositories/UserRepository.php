<?php

namespace App\Repositories;


use App\Models\User;
use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserRepository implements RepositoryInterface
{
    public function all(array $filters = []): LengthAwarePaginator
    {
        $query = User::query();

        if (isset($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }
        if (isset($filters['email'])) {
            $query->where('email', 'like', '%' . $filters['email'] . '%');
        }

        $limit = $filters['limit'] ?? 10;
        return $query->paginate($limit);
    }

    public function create(array $data)
    : User {
        return User::create($data);
    }

    public function update($id, array $data)
    : ?User {
        $user = User::find($id);
        if (!$user) {
            return null;
        }
        $user->update($data);
        return $user;
    }

    public function findById($id)
    : ?User {
        return User::find($id);
    }

    public function delete($id)
    : bool {
        $user = User::find($id);
        if (!$user) {
            return false;
        }
        return $user->delete();
    }
}
