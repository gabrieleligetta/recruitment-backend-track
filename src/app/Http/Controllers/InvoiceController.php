<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class InvoiceController extends Controller
{
    protected InvoiceService $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->middleware('auth:api');
        $this->invoiceService = $invoiceService;
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['invoice_number', 'status', 'limit']);
        $authUser = $this->getAuthUser();
        // Non-admin users see only their own invoices.
        if ($authUser->role !== 'admin') {
            $filters['user_id'] = $authUser->id;
        }
        $invoices = $this->invoiceService->getAll($filters);
        return response()->json($invoices, ResponseAlias::HTTP_OK);
    }

    /**
     * Retrieve the authenticated user.
     *
     * @return User
     */
    private function getAuthUser(): User
    {
        /** @var User $user */
        $user = auth()->user();
        return $user;
    }

    // GET /api/invoices?invoice_number=...&status=...&limit=...

    public function show($id): JsonResponse
    {
        $invoice = $this->invoiceService->getById($id);
        if (!$invoice) {
            return response()->json(['message' => 'Invoice not found'], ResponseAlias::HTTP_NOT_FOUND);
        }
        // Ensure that only admins or the owner can view the invoice.
        $this->authorizeOwnerOrAdmin($invoice->user_id);
        return response()->json($invoice, ResponseAlias::HTTP_OK);
    }

    // GET /api/invoices/{id}

    /**
     * Ensure that the authenticated user is allowed to manage a resource,
     * either by being an admin or by owning the resource.
     *
     * @param int $resourceUserId
     * @return void
     */
    private function authorizeOwnerOrAdmin(int $resourceUserId): void
    {
        $authUser = $this->getAuthUser();
        if ($authUser->role !== 'admin' && $authUser->id !== $resourceUserId) {
            abort(ResponseAlias::HTTP_FORBIDDEN, 'Forbidden');
        }
    }

    // POST /api/invoices

    public function store(Request $request): JsonResponse
    {
        $authUser = $this->getAuthUser();
        $rules = [
            'tax_profile_id' => 'required|exists:tax_profiles,id',
            'invoice_number' => 'required|string|unique:invoices,invoice_number',
            'description'    => 'required|string',
            'invoice_date'   => 'required|date',
            'total_amount'   => 'required|numeric',
            'status'         => 'sometimes|required|in:pending,paid,canceled',
        ];
        $validatedData = $request->validate($rules);

        // For non-admins, assign their own user_id.
        if ($authUser->role !== 'admin') {
            $validatedData['user_id'] = $authUser->id;
        } else {
            // For admins, optionally allow a different user_id.
            $validatedData['user_id'] = $request->input('user_id', $authUser->id);
        }
        $invoice = $this->invoiceService->create($validatedData);
        return response()->json($invoice, ResponseAlias::HTTP_CREATED);
    }

    // PUT /api/invoices/{id}
    public function update(Request $request, $id): JsonResponse
    {
        $invoice = $this->invoiceService->getById($id);
        if (!$invoice) {
            return response()->json(['message' => 'Invoice not found'], ResponseAlias::HTTP_NOT_FOUND);
        }
        // Authorize the action: only admins or the owner can update.
        $this->authorizeOwnerOrAdmin($invoice->user_id);

        $rules = [
            'tax_profile_id' => 'sometimes|required|exists:tax_profiles,id',
            'invoice_number' => 'sometimes|required|string|unique:invoices,invoice_number,' . $id,
            'description'    => 'sometimes|required|string',
            'invoice_date'   => 'sometimes|required|date',
            'total_amount'   => 'sometimes|required|numeric',
            'status'         => 'sometimes|required|in:pending,paid,canceled',
        ];
        $validatedData = $request->validate($rules);

        // For non-admins, ensure they cannot modify user_id.
        if ($this->getAuthUser()->role !== 'admin') {
            unset($validatedData['user_id']);
        }
        $updatedInvoice = $this->invoiceService->update($id, $validatedData);
        return response()->json($updatedInvoice, ResponseAlias::HTTP_OK);
    }

    // DELETE /api/invoices/{id}
    public function destroy($id): JsonResponse
    {
        $invoice = $this->invoiceService->getById($id);
        if (!$invoice) {
            return response()->json(['message' => 'Invoice not found'], ResponseAlias::HTTP_NOT_FOUND);
        }
        // Only admins or owners can delete.
        $this->authorizeOwnerOrAdmin($invoice->user_id);
        $deleted = $this->invoiceService->delete($id);
        return response()->json(['message' => 'Invoice deleted'], ResponseAlias::HTTP_OK);
    }
}
