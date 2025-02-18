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

    // POST /api/invoices/list
    public function list(Request $request): JsonResponse
    {
        // Prefer JSON payload if available.
        $data = $request->json()->all();
        if (empty($data)) {
            // Fall back to query parameters.
            $data = $request->query();
        }

        // If using the JSON structure, merge filters and sorting into a single array.
        $params = [];
        if (isset($data['filters']) && is_array($data['filters'])) {
            $params = array_merge($params, $data['filters']);
        }
        if (isset($data['sort']) && is_array($data['sort'])) {
            // We'll reserve 'sort' as two keys: sort_by and sort_dir.
            $params['sort_by'] = $data['sort']['field'] ?? null;
            $params['sort_dir'] = $data['sort']['direction'] ?? 'asc';
        }
        if (isset($data['limit'])) {
            $params['limit'] = $data['limit'];
        }

        // Let the service handle filtering/sorting.
        $users = $this->invoiceService->getAll($params);
        return response()->json($users, ResponseAlias::HTTP_OK);
    }

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

    // GET /api/invoices?invoice_number=...&status=...&limit=...

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

    // GET /api/invoices/{id}

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
