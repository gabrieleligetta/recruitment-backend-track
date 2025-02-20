<?php

namespace App\Http\Controllers;

use App\Services\InvoiceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Throwable;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Invoice", description: "Operations related to invoices")]
class InvoiceController extends Controller
{
    protected InvoiceService $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->middleware('auth:api');
        $this->invoiceService = $invoiceService;
    }

    #[OA\Post(
        path: "/api/invoice/list",
        description: "Returns a list of invoices based on filtering, sorting, and pagination options. Requires authentication.",
        summary: "List Invoices",
        security: [["bearerAuth" => []] ],
        requestBody: new OA\RequestBody(
            description: "Filtering, sorting, and pagination parameters",
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/PaginatedListFilter")
        ),
        tags: ["Invoice"],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of invoices",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(ref: "#/components/schemas/Invoice")
                )
            )
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        try {
            $authUser = $this->getAuthenticatedUser();
            $data = $request->json()->all() ?: $request->query();

            return response()->json($this->invoiceService->getAll($authUser, $data), ResponseAlias::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Error fetching invoice list', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Server Error'], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Get(
        path: "/api/invoice/{id}",
        description: "Retrieve an invoice by its ID. Requires authentication.",
        summary: "Get Invoice",
        security: [["bearerAuth" => []] ],
        tags: ["Invoice"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "Invoice ID",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Invoice found",
                content: new OA\JsonContent(ref: "#/components/schemas/Invoice")
            ),
            new OA\Response(
                response: 403,
                description: "Forbidden",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Forbidden")
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: "Server error",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Server Error"),
                        new OA\Property(property: "error", type: "string", example: "Error details...")
                    ]
                )
            )
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $authUser = $this->getAuthenticatedUser();
            return response()->json($this->invoiceService->getById($authUser, $id), ResponseAlias::HTTP_OK);
        } catch (AuthorizationException $e) {
            Log::error('Error retrieving invoice', ['invoice_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Forbidden'], ResponseAlias::HTTP_FORBIDDEN);
        } catch (Throwable $e) {
            Log::error('Error retrieving invoice', ['invoice_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Server Error'], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: "/api/invoice",
        description: "Creates a new invoice. Requires authentication.",
        summary: "Create Invoice",
        security: [["bearerAuth" => []] ],
        requestBody: new OA\RequestBody(
            description: "Invoice data",
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/Invoice")
        ),
        tags: ["Invoice"],
        responses: [
            new OA\Response(
                response: 201,
                description: "Invoice created successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/Invoice")
            ),
            new OA\Response(
                response: 403,
                description: "Forbidden",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Forbidden")
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Validation errors",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "object", example: "{ 'field': ['Error message'] }")
                    ]
                )
            )
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        try {
            $authUser = $this->getAuthenticatedUser();
            return response()->json($this->invoiceService->create($authUser, $request->all()), ResponseAlias::HTTP_CREATED);
        } catch (AuthorizationException $e) {
            Log::error('Error creating invoice', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Forbidden'], ResponseAlias::HTTP_FORBIDDEN);
        } catch (ValidationException $e) {
            Log::error('Error creating invoice', ['error' => $e->getMessage()]);
            return response()->json(['message' => $e->errors()], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $e) {
            Log::error('Error creating invoice', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Server Error'], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Put(
        path: "/api/invoice/{id}",
        description: "Updates an existing invoice. Requires authentication.",
        summary: "Update Invoice",
        security: [["bearerAuth" => []] ],
        requestBody: new OA\RequestBody(
            description: "Updated invoice data",
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/Invoice")
        ),
        tags: ["Invoice"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "Invoice ID",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Invoice updated successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/Invoice")
            ),
            new OA\Response(
                response: 403,
                description: "Forbidden",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Forbidden")
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Validation errors",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "object", example: "{ 'field': ['Error message'] }")
                    ]
                )
            )
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $authUser = $this->getAuthenticatedUser();
            return response()->json($this->invoiceService->update($authUser, $id, $request->all()), ResponseAlias::HTTP_OK);
        } catch (AuthorizationException $e) {
            Log::error('Error updating invoice', ['invoice_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Forbidden'], ResponseAlias::HTTP_FORBIDDEN);
        } catch (ValidationException $e) {
            Log::error('Error updating invoice', ['invoice_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => $e->errors()], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $e) {
            Log::error('Error updating invoice', ['invoice_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Server Error'], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Delete(
        path: "/api/invoice/{id}",
        description: "Deletes an invoice by its ID. Requires authentication.",
        summary: "Delete Invoice",
        security: [["bearerAuth" => []] ],
        tags: ["Invoice"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "Invoice ID",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Invoice deleted successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Invoice deleted")
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Invoice not found",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Invoice not found")
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: "Forbidden",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Forbidden")
                    ]
                )
            )
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $authUser = $this->getAuthenticatedUser();
            return $this->invoiceService->delete($authUser, $id)
                ? response()->json(['message' => 'Invoice deleted'], ResponseAlias::HTTP_OK)
                : response()->json(['message' => 'Invoice not found'], ResponseAlias::HTTP_NOT_FOUND);
        } catch (AuthorizationException $e) {
            Log::error('Error deleting invoice', ['invoice_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Forbidden'], ResponseAlias::HTTP_FORBIDDEN);
        } catch (Throwable $e) {
            Log::error('Error deleting invoice', ['invoice_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Server Error'], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

