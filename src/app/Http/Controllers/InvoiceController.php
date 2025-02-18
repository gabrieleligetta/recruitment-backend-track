<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Throwable;

class InvoiceController extends Controller
{
    protected InvoiceService $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->middleware('auth:api');
        $this->invoiceService = $invoiceService;
    }

    // POST /api/invoice/list
    public function list(Request $request): JsonResponse
    {
        $authUser = $this->getAuthenticatedUser();
        $data = $request->json()->all() ?: $request->query();

        $invoices = $this->invoiceService->getAll($authUser, $data);

        return response()->json($invoices, ResponseAlias::HTTP_OK);
    }

    // GET /api/invoice/{id}
    public function show(int $id): JsonResponse
    {
        try {
            $authUser = $this->getAuthenticatedUser();
            $invoice = $this->invoiceService->getById($authUser, $id);
            return response()->json($invoice, ResponseAlias::HTTP_OK);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => 'Forbidden'], ResponseAlias::HTTP_FORBIDDEN);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Server Error', 'error' => $e->getMessage()], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // POST /api/invoice
    public function store(Request $request): JsonResponse
    {
        try {
            $authUser = $this->getAuthenticatedUser();
            $invoice = $this->invoiceService->create($authUser, $request->all());
            return response()->json($invoice, ResponseAlias::HTTP_CREATED);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => 'Forbidden'], ResponseAlias::HTTP_FORBIDDEN);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->errors()], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    // PUT /api/invoice/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $authUser = $this->getAuthenticatedUser();
            $invoice = $this->invoiceService->update($authUser, $id, $request->all());
            return response()->json($invoice, ResponseAlias::HTTP_OK);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => 'Forbidden'], ResponseAlias::HTTP_FORBIDDEN);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->errors()], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    // DELETE /api/invoice/{id}
    public function destroy(int $id): JsonResponse
    {
        try {
            $authUser = $this->getAuthenticatedUser();
            return $this->invoiceService->delete($authUser, $id)
                ? response()->json(['message' => 'Invoice deleted'], ResponseAlias::HTTP_OK)
                : response()->json(['message' => 'Invoice not found'], ResponseAlias::HTTP_NOT_FOUND);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => 'Forbidden'], ResponseAlias::HTTP_FORBIDDEN);
        }
    }
}
