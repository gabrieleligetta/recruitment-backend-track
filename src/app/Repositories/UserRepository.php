<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\RepositoryInterface;

class UserRepository extends GeneralRepository implements RepositoryInterface
{
    protected function model(): string
    {
        return User::class;
    }
}
