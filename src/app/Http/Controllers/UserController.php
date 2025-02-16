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
        $this->middleware('access:open')->only(['index']);
        $this->middleware('access:self')->only(['show', 'update']);
        $this->middleware('access:admin')->only(['destroy']);
    }

    // GET /api/user?name=...&email=...&limit=...
    public function index(Request $request)
    : JsonResponse {
         $filters = $request->only(['name', 'email', 'limit']);
         $users = $this->userService->getAll($filters);
         return response()->json($users, ResponseAlias::HTTP_OK);
    }

    // GET /api/user/{id}
    public function show($id)
    : JsonResponse {
         $user = $this->userService->getById($id);
         if (!$user) {
             return response()->json(['message' => 'User not found'], ResponseAlias::HTTP_NOT_FOUND);
         }
         return response()->json($user, ResponseAlias::HTTP_OK);
    }

    // PUT /api/user/{id}
    public function update(Request $request, $id)
    : JsonResponse {
         $validatedData = $request->validate([
             'name'     => 'sometimes|required|string|max:255',
             'email'    => 'sometimes|required|email|unique:users,email,' . $id,
             'password' => 'sometimes|required|string|min:6',
         ]);

         $user = $this->userService->update($id, $validatedData);
         if (!$user) {
             return response()->json(['message' => 'User not found'], ResponseAlias::HTTP_NOT_FOUND);
         }
         return response()->json($user, ResponseAlias::HTTP_OK);
    }

    // DELETE /api/user/{id}
    public function destroy($id)
    : JsonResponse {
         $deleted = $this->userService->delete($id);
         if (!$deleted) {
             return response()->json(['message' => 'User not found'], ResponseAlias::HTTP_NOT_FOUND);
         }
         return response()->json(['message' => 'User deleted'], ResponseAlias::HTTP_OK);
    }
}
