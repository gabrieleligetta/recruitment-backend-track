<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\Gate;
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

    // POST /api/user
    public function store(Request $request)
    : JsonResponse {
        Gate::allowIf(fn (User $authUser) => $authUser->isAdministrator());

        $validatedData = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = $this->userService->create($validatedData);
        return response()->json($user, ResponseAlias::HTTP_CREATED);
    }

    // PUT /api/user/{id}
    public function update(Request $request, $id)
    : JsonResponse {

        Gate::allowIf(fn (User $authUser) => $authUser->isAdministrator() || $authUser->id == $id);

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
        Gate::denyIf(fn (User $authUser) => !$authUser->isAdministrator());
         $deleted = $this->userService->delete($id);
         if (!$deleted) {
             return response()->json(['message' => 'User not found'], ResponseAlias::HTTP_NOT_FOUND);
         }
         return response()->json(['message' => 'User deleted'], ResponseAlias::HTTP_OK);
    }
}
