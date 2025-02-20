<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use OpenApi\Attributes as OA;
use Throwable;

#[OA\Tag(name: "User", description: "Operations about users")]
class UserController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
        $this->middleware('auth:api');
    }

    #[OA\Post(
        path: "/api/user/list",
        description: "Returns a list of users based on filter parameters. Requires authentication.",
        summary: "List Users",
        security: [["bearerAuth" => []] ],
        requestBody: new OA\RequestBody(
            description: "Filtering, sorting, and pagination parameters",
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/PaginatedListFilter")
        ),
        tags: ["User"],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of users",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(ref: "#/components/schemas/User")
                )
            )
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        try {
            $authUser = $this->getAuthenticatedUser();
            $data = $request->json()->all() ?: $request->query();
            return response()->json($this->userService->getAll($authUser, $data), ResponseAlias::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Error fetching user list', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Server Error'], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Get(
        path: "/api/user/{id}",
        description: "Returns a single user by ID. Requires authentication.",
        summary: "Get User",
        security: [["bearerAuth" => []] ],
        tags: ["User"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "ID of the user",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "User found",
                content: new OA\JsonContent(ref: "#/components/schemas/User")
            ),
            new OA\Response(
                response: 404,
                description: "User not found",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "error", type: "string", example: "User not found")
                    ]
                )
            )
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $user = $this->userService->getById($id);
            return $user
                ? response()->json($user, ResponseAlias::HTTP_OK)
                : $this->errorResponse('User not found', ResponseAlias::HTTP_NOT_FOUND);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => 'Forbidden'], ResponseAlias::HTTP_FORBIDDEN);
        } catch (Throwable $e) {
            Log::error('Error retrieving user', ['user_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Server Error'], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: "/api/user",
        description: "Creates a new user. Requires admin privileges.",
        summary: "Create User",
        security: [["bearerAuth" => []] ],
        requestBody: new OA\RequestBody(
            description: "User data to create a new user",
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "password"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "John Doe"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "secret")
                ]
            )
        ),
        tags: ["User"],
        responses: [
            new OA\Response(
                response: 201,
                description: "User created successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/User")
            )
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        try {
            $authUser = $this->getAuthenticatedUser();
            $this->userService->authorizeAdmin($authUser);
            return response()->json($this->userService->create($request->all()), ResponseAlias::HTTP_CREATED);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => 'Forbidden'], ResponseAlias::HTTP_FORBIDDEN);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->errors()], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $e) {
            Log::error('Error creating user', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Server Error'], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Put(
        path: "/api/user/{id}",
        description: "Updates an existing user. Requires authentication and proper authorization.",
        summary: "Update User",
        security: [["bearerAuth" => []] ],
        requestBody: new OA\RequestBody(
            description: "User data for update",
            required: true,
            content: new OA\JsonContent(
                type: "object"
            // Optionally, list the updatable properties
            )
        ),
        tags: ["User"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "ID of the user to update",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "User updated successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/User")
            ),
            new OA\Response(
                response: 404,
                description: "User not found",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "error", type: "string", example: "User not found")
                    ]
                )
            )
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $authUser = $this->getAuthenticatedUser();
            $this->userService->authorizeAdminOrOwner($authUser, $id);
            $user = $this->userService->update($id, $request->all());

            return $user
                ? response()->json($user, ResponseAlias::HTTP_OK)
                : $this->errorResponse('User not found', ResponseAlias::HTTP_NOT_FOUND);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => 'Forbidden'], ResponseAlias::HTTP_FORBIDDEN);
        } catch (Throwable $e) {
            Log::error('Error updating user', ['user_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Server Error'], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Delete(
        path: "/api/user/{id}",
        description: "Deletes a user by ID. Requires admin privileges.",
        summary: "Delete User",
        security: [["bearerAuth" => []] ],
        tags: ["User"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "ID of the user to delete",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "User deleted successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "User deleted")
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "User not found",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "error", type: "string", example: "User not found")
                    ]
                )
            )
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $authUser = $this->getAuthenticatedUser();
            $this->userService->authorizeAdmin($authUser);

            return $this->userService->delete($id)
                ? response()->json(['message' => 'User deleted'], ResponseAlias::HTTP_OK)
                : $this->errorResponse('User not found', ResponseAlias::HTTP_NOT_FOUND);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => 'Forbidden'], ResponseAlias::HTTP_FORBIDDEN);
        } catch (Throwable $e) {
            Log::error('Error deleting user', ['user_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Server Error'], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
