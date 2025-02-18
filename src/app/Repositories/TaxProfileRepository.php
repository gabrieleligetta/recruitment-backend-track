<?php

namespace App\Repositories;

use App\Models\TaxProfile;
use App\Repositories\Contracts\RepositoryInterface;

class TaxProfileRepository extends GeneralRepository implements RepositoryInterface
{
    protected function model(): string
    {
        return TaxProfile::class;
    }
}
