<?php

namespace App\Repositories;

use App\Models\Invoice;
use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InvoiceRepository implements RepositoryInterface
{
    public function all(array $filters = []): LengthAwarePaginator
    {
        $query = Invoice::query();

        if (isset($filters['invoice_number'])) {
            $query->where('invoice_number', 'like', '%' . $filters['invoice_number'] . '%');
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $limit = $filters['limit'] ?? 10;
        return $query->paginate($limit);
    }

    public function create(array $data): Invoice
    {
        return Invoice::create($data);
    }

    public function update($id, array $data): ?Invoice
    {
        $invoice = Invoice::find($id);
        if (!$invoice) {
            return null;
        }
        $invoice->update($data);
        return $invoice;
    }

    public function findById($id): ?Invoice
    {
        return Invoice::find($id);
    }

    public function delete($id): bool
    {
        $invoice = Invoice::find($id);
        if (!$invoice) {
            return false;
        }
        return $invoice->delete();
    }
}
