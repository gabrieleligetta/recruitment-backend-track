<?php

namespace App\Repositories;

use App\Models\Invoice;
use App\Repositories\Contracts\RepositoryInterface;

class InvoiceRepository extends GeneralRepository implements RepositoryInterface
{
    protected function model(): string
    {
        return Invoice::class;
    }
}
