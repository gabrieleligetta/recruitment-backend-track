<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TaxProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class TaxProfileController extends Controller
{
    protected TaxProfileService $taxProfileService;

    public function __construct(TaxProfileService $taxProfileService)
    {
        $this->middleware('auth:api');
        $this->taxProfileService = $taxProfileService;
    }

    // POST /api/tax-profiles/list
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
        $users = $this->taxProfileService->getAll($params);
        return response()->json($users, ResponseAlias::HTTP_OK);
    }

    public function show($id): JsonResponse
    {
        $profile = $this->taxProfileService->getById($id);
        if (!$profile) {
            return response()->json(['message' => 'Tax Profile not found'], ResponseAlias::HTTP_NOT_FOUND);
        }
        // Ensure only admins or the owner can view the profile.
        $this->authorizeOwnerOrAdmin($profile->user_id);
        return response()->json($profile, ResponseAlias::HTTP_OK);
    }

    // GET /api/tax-profiles?tax_id=...&company_name=...&limit=...

    /**
     * Authorize that the current user owns the resource or is an admin.
     *
     * @param  int  $resourceUserId
     * @return void
     */
    private function authorizeOwnerOrAdmin(int $resourceUserId): void
    {
        $authUser = $this->getAuthUser();
        if ($authUser->role !== 'admin' && $authUser->id !== $resourceUserId) {
            abort(ResponseAlias::HTTP_FORBIDDEN, 'Forbidden');
        }
    }

    // GET /api/tax-profiles/{id}

    /**
     * Retrieve the currently authenticated user, typed as User.
     *
     * @return User
     */
    private function getAuthUser(): User
    {
        /** @var User $user */
        $user = auth()->user();
        return $user;
    }

    // POST /api/tax-profiles

    public function store(Request $request): JsonResponse
    {
        $authUser = $this->getAuthUser();
        $rules = [
            'tax_id'       => 'required|string|unique:tax_profiles,tax_id',
            'company_name' => 'required|string|max:255',
            'address'      => 'required|string',
            'country'      => 'required|string|max:100',
            'city'         => 'required|string|max:100',
            'zip_code'     => 'required|string|max:20',
        ];
        // Admins may specify a user_id; non-admins use their own.
        if ($authUser->role === 'admin') {
            $rules['user_id'] = 'required|exists:users,id';
        }
        $validatedData = $request->validate($rules);
        if ($authUser->role !== 'admin') {
            $validatedData['user_id'] = $authUser->id;
        }
        $profile = $this->taxProfileService->create($validatedData);
        return response()->json($profile, ResponseAlias::HTTP_CREATED);
    }

    // PUT /api/tax-profiles/{id}
    public function update(Request $request, $id): JsonResponse
    {
        $profile = $this->taxProfileService->getById($id);
        if (!$profile) {
            return response()->json(['message' => 'Tax Profile not found'], ResponseAlias::HTTP_NOT_FOUND);
        }
        // Authorize that only admins or the owner can update.
        $this->authorizeOwnerOrAdmin($profile->user_id);
        $rules = [
            'tax_id'       => 'sometimes|required|string|unique:tax_profiles,tax_id,' . $id,
            'company_name' => 'sometimes|required|string|max:255',
            'address'      => 'sometimes|required|string',
            'country'      => 'sometimes|required|string|max:100',
            'city'         => 'sometimes|required|string|max:100',
            'zip_code'     => 'sometimes|required|string|max:20',
        ];
        // Only admins can change the user_id.
        if ($this->getAuthUser()->role === 'admin') {
            $rules['user_id'] = 'sometimes|required|exists:users,id';
        }
        $validatedData = $request->validate($rules);
        if ($this->getAuthUser()->role !== 'admin') {
            unset($validatedData['user_id']);
        }
        $updatedProfile = $this->taxProfileService->update($id, $validatedData);
        return response()->json($updatedProfile, ResponseAlias::HTTP_OK);
    }

    // DELETE /api/tax-profiles/{id}
    public function destroy($id): JsonResponse
    {
        $profile = $this->taxProfileService->getById($id);
        if (!$profile) {
            return response()->json(['message' => 'Tax Profile not found'], ResponseAlias::HTTP_NOT_FOUND);
        }
        // Only allow deletion if the authenticated user is the owner or an admin.
        $this->authorizeOwnerOrAdmin($profile->user_id);
        $deleted = $this->taxProfileService->delete($id);
        return response()->json(['message' => 'Tax Profile deleted'], ResponseAlias::HTTP_OK);
    }
}
