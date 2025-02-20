<?php

namespace App\Http\Controllers;

use App\Services\TaxProfileService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HTTPCode;
use OpenApi\Attributes as OA;
use Throwable;

#[OA\Tag(name: "TaxProfile", description: "Operations for managing tax profiles")]
class TaxProfileController extends Controller
{
    protected TaxProfileService $taxProfileService;

    public function __construct(TaxProfileService $taxProfileService)
    {
        $this->middleware('auth:api');
        $this->taxProfileService = $taxProfileService;
    }

    #[OA\Post(
        path: "/api/tax-profile/list",
        description: "Returns a list of tax profiles based on provided filters, sort, and limit parameters. Requires authentication.",
        summary: "List Tax Profiles",
        security: [["bearerAuth" => []] ],
        requestBody: new OA\RequestBody(
            description: "Filtering, sorting, and pagination parameters",
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/PaginatedListFilter")
        ),
        tags: ["TaxProfile"],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of tax profiles",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(ref: "#/components/schemas/TaxProfile")
                )
            )
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        try {
            $authUser = $this->getAuthenticatedUser();
            $data = $request->json()->all() ?: $request->query();
            return response()->json($this->taxProfileService->getAll($authUser, $data), HTTPCode::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Error fetching tax profiles', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Server Error'], HTTPCode::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Get(
        path: "/api/tax-profile/{id}",
        description: "Retrieve a specific tax profile by its ID. Requires authentication.",
        summary: "Get Tax Profile",
        security: [["bearerAuth" => []] ],
        tags: ["TaxProfile"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "ID of the tax profile",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Tax profile found",
                content: new OA\JsonContent(ref: "#/components/schemas/TaxProfile")
            ),
            new OA\Response(
                response: 404,
                description: "Tax Profile not found",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Tax Profile not found")
                    ]
                )
            )
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $authUser = $this->getAuthenticatedUser();
            $profile = $this->taxProfileService->getById($authUser, $id);

            return $profile
                ? response()->json($profile, HTTPCode::HTTP_OK)
                : $this->errorResponse('Tax Profile not found', HTTPCode::HTTP_NOT_FOUND);
        }
        catch (AuthorizationException $e) {
            Log::error('Error creating tax profile', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Forbidden'], HTTPCode::HTTP_FORBIDDEN);
        }
        catch (Throwable $e) {
            Log::error('Error retrieving tax profile', ['tax_profile_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Server Error'], HTTPCode::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: "/api/tax-profile",
        description: "Creates a new tax profile for the authenticated user.",
        summary: "Create Tax Profile",
        security: [["bearerAuth" => []] ],
        requestBody: new OA\RequestBody(
            description: "Tax profile details",
            required: true,
            content: new OA\JsonContent(
                required: ["tax_id", "company_name", "address", "country", "city", "zip_code"],
                properties: [
                    new OA\Property(property: "tax_id", type: "string", example: "TAX123456"),
                    new OA\Property(property: "company_name", type: "string", example: "Acme Inc."),
                    new OA\Property(property: "address", type: "string", example: "123 Main Street"),
                    new OA\Property(property: "country", type: "string", example: "USA"),
                    new OA\Property(property: "city", type: "string", example: "New York"),
                    new OA\Property(property: "zip_code", type: "string", example: "10001")
                ]
            )
        ),
        tags: ["TaxProfile"],
        responses: [
            new OA\Response(
                response: 201,
                description: "Tax profile created successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/TaxProfile")
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
                        new OA\Property(property: "message", type: "object", example: "{ 'tax_id': ['The tax_id field is required.'] }")
                    ]
                )
            )
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        try {
            $authUser = $this->getAuthenticatedUser();
            $profile = $this->taxProfileService->create($authUser, $request->all());

            return response()->json($profile, HTTPCode::HTTP_CREATED);
        } catch (AuthorizationException $e) {
            Log::error('Error creating tax profile', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Forbidden'], HTTPCode::HTTP_FORBIDDEN);
        } catch (ValidationException $e) {
            Log::error('Error creating tax profile', ['error' => $e->getMessage()]);
            return response()->json(['message' => $e->errors()], HTTPCode::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $e) {
            Log::error('Error creating tax profile', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Server Error'], HTTPCode::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Put(
        path: "/api/tax-profile/{id}",
        description: "Updates an existing tax profile. Requires authentication.",
        summary: "Update Tax Profile",
        security: [["bearerAuth" => []] ],
        requestBody: new OA\RequestBody(
            description: "Tax profile data to update",
            required: true,
            content: new OA\JsonContent(
                type: "object"
            // Optionally define updatable properties here
            )
        ),
        tags: ["TaxProfile"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "ID of the tax profile to update",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Tax profile updated successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/TaxProfile")
            ),
            new OA\Response(
                response: 404,
                description: "Tax Profile not found",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Tax Profile not found")
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
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $authUser = $this->getAuthenticatedUser();
            $profile = $this->taxProfileService->update($authUser, $id, $request->all());

            return $profile
                ? response()->json($profile, HTTPCode::HTTP_OK)
                : $this->errorResponse('Tax Profile not found', HTTPCode::HTTP_NOT_FOUND);
        } catch (AuthorizationException $e) {
            Log::error('Error updating tax profile', ['tax_profile_id' => $id, 'error' => $e->getMessage()]);
            return $this->errorResponse('Forbidden', HTTPCode::HTTP_FORBIDDEN);
        } catch (Throwable $e) {
            Log::error('Error updating tax profile', ['tax_profile_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Server Error'], HTTPCode::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Delete(
        path: "/api/tax-profile/{id}",
        description: "Deletes a tax profile by its ID. Requires authentication.",
        summary: "Delete Tax Profile",
        security: [["bearerAuth" => []] ],
        tags: ["TaxProfile"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "ID of the tax profile to delete",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Tax profile deleted successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Tax Profile deleted")
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Tax Profile not found",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Tax Profile not found")
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

            return $this->taxProfileService->delete($authUser, $id)
                ? response()->json(['message' => 'Tax Profile deleted'], HTTPCode::HTTP_OK)
                : $this->errorResponse('Tax Profile not found', HTTPCode::HTTP_NOT_FOUND);
        } catch (AuthorizationException $e) {
            Log::error('Error deleting tax profile', ['tax_profile_id' => $id, 'error' => $e->getMessage()]);
            return $this->errorResponse('Forbidden', HTTPCode::HTTP_FORBIDDEN);
        } catch (Throwable $e) {
            Log::error('Error deleting tax profile', ['tax_profile_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Server Error'], HTTPCode::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
