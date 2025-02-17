<?php

namespace App\Services;

use App\Models\Invoice;
use App\Repositories\InvoiceRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InvoiceService
{
    protected InvoiceRepository $invoiceRepository;

    public function __construct(InvoiceRepository $invoiceRepository)
    {
        $this->invoiceRepository = $invoiceRepository;
    }

    /**
     * Get a paginated list of invoices.
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        return $this->invoiceRepository->all($filters);
    }

    /**
     * Find an Invoice by its ID.
     *
     * @param mixed $id
     * @return Invoice|null
     */
    public function getById($id): ?Invoice
    {
        return $this->invoiceRepository->findById($id);
    }

    /**
     * Create a new Invoice.
     *
     * @param array $data
     * @return Invoice
     */
    public function create(array $data): Invoice
    {
        return $this->invoiceRepository->create($data);
    }

    /**
     * Update an existing Invoice.
     *
     * @param mixed $id
     * @param array $data
     * @return Invoice|null
     */
    public function update($id, array $data): ?Invoice
    {
        return $this->invoiceRepository->update($id, $data);
    }

    /**
     * Delete an Invoice.
     *
     * @param mixed $id
     * @return bool
     */
    public function delete($id): bool
    {
        return $this->invoiceRepository->delete($id);
    }
}
