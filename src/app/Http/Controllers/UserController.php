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

    // POST /api/user/list
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
        $users = $this->userService->getAll($params);
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
