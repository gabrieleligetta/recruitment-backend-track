<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\User;
use App\Repositories\InvoiceRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

class InvoiceService extends GeneralService
{
    protected InvoiceRepository $invoiceRepository;

    public function __construct(InvoiceRepository $invoiceRepository)
    {
        $this->invoiceRepository = $invoiceRepository;
    }

    /**
     * Get a paginated list of invoices.
     */
    public function getAll(User $authUser, array $requestData = []): LengthAwarePaginator
    {
        $filters = $this->prepareFilters($authUser, $requestData);
        return $this->invoiceRepository->all($filters);
    }

    /**
     * Create a new Invoice.
     */
    public function create(User $authUser, array $data): Invoice
    {
        // ✅ Validate the data
        $validatedData = $this->validateInvoice($data, false);

        // ✅ Ensure non-admins can only create their own invoices
        if ($authUser->role !== 'admin') {
            $validatedData['user_id'] = $authUser->id;
        }

        return $this->invoiceRepository->create($validatedData);
    }

    /**
     * Validate invoice input.
     */
    private function validateInvoice(array $data, bool $isUpdate = false, ?int $id = null): array
    {
        $rules = [
            'user_id'        => $isUpdate ? 'sometimes|required|exists:users,id' : 'required|exists:users,id',
            'tax_profile_id' => $isUpdate ? 'sometimes|required|exists:tax_profiles,id' : 'required|exists:tax_profiles,id',
            'invoice_number' => $isUpdate ? "sometimes|required|string|unique:invoices,invoice_number,{$id}" : 'required|string|unique:invoices,invoice_number',
            'description'    => $isUpdate ? 'sometimes|required|string' : 'required|string',
            'invoice_date'   => $isUpdate ? 'sometimes|required|date' : 'required|date',
            'total_amount'   => $isUpdate ? 'sometimes|required|numeric' : 'required|numeric',
            'status'         => $isUpdate ? 'sometimes|required|in:pending,paid,canceled' : 'required|in:pending,paid,canceled',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }


    /**
     * Update an existing Invoice.
     */
    public function update(User $authUser, int $id, array $data): ?Invoice
    {
        $invoice = $this->getById($authUser, $id);
        if (!$invoice) return null;

        // ✅ Ensure only admins or the owner can update the invoice
        $this->authorizeAdminOrOwner($authUser, $invoice->user_id);

        $validatedData = $this->validateInvoice($data, true, $id);
        return $this->invoiceRepository->update($id, $validatedData);
    }

    /**
     * Find an Invoice by its ID.
     */
    public function getById(User $authUser, int $id): ?Invoice
    {
        $invoice = $this->invoiceRepository->findById($id);

        if (!$invoice) {
            return null;
        }

        // ✅ Ensure only admins or the owner can view the invoice
        $this->authorizeAdminOrOwner($authUser, $invoice->user_id);

        return $invoice;
    }

    /**
     * Delete an Invoice.
     */
    public function delete(User $authUser, int $id): bool
    {
        $invoice = $this->getById($authUser, $id);
        if (!$invoice) return false;

        // ✅ Ensure only admins or the owner can delete the invoice
        $this->authorizeAdminOrOwner($authUser, $invoice->user_id);

        return $this->invoiceRepository->delete($id);
    }
}
