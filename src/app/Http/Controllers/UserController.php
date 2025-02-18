<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class UserController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
        $this->middleware('auth:api');
    }

    // POST /api/user/list
    public function list(Request $request): JsonResponse
    {
        $authUser = $this->getAuthenticatedUser();
        $data = $request->json()->all() ?: $request->query();
        $users = $this->userService->getAll($authUser, $data);

        return response()->json($users, ResponseAlias::HTTP_OK);
    }

    // GET /api/user/{id}
    public function show(int $id): JsonResponse
    {
        $user = $this->userService->getById($id);
        return $user
            ? response()->json($user, ResponseAlias::HTTP_OK)
            : $this->errorResponse('User not found', ResponseAlias::HTTP_NOT_FOUND);
    }


    // PUT /api/user/{id}
    public function store(Request $request): JsonResponse
    {
        $authUser = $this->getAuthenticatedUser();
        $this->userService->authorizeAdmin($authUser);
        $user = $this->userService->create($request->all());
        return response()->json($user, ResponseAlias::HTTP_CREATED);
    }

    // DELETE /api/user/{id}

    public function update(Request $request, int $id): JsonResponse
    {
        $authUser = $this->getAuthenticatedUser();
        $this->userService->authorizeAdminOrOwner($authUser, $id);
        $user = $this->userService->update($id, $request->all());
        return $user
            ? response()->json($user, ResponseAlias::HTTP_OK)
            : $this->errorResponse('User not found', ResponseAlias::HTTP_NOT_FOUND);
    }

    public function destroy(int $id): JsonResponse
    {
        $authUser = $this->getAuthenticatedUser();
        $this->userService->authorizeAdmin($authUser);

        return $this->userService->delete($id)
            ? response()->json(['message' => 'User deleted'], ResponseAlias::HTTP_OK)
            : $this->errorResponse('User not found', ResponseAlias::HTTP_NOT_FOUND);
    }
}
