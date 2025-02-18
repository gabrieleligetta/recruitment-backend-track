<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TaxProfileService;
use Illuminate\Auth\Access\AuthorizationException;
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

    // POST /api/tax-profile/list
    public function list(Request $request): JsonResponse
    {
        $authUser = $this->getAuthenticatedUser();
        $data = $request->json()->all() ?: $request->query();

        $taxProfiles = $this->taxProfileService->getAll($authUser, $data);

        return response()->json($taxProfiles, ResponseAlias::HTTP_OK);
    }

    // GET /api/tax-profile/{id}
    public function show(int $id): JsonResponse
    {
        $authUser = $this->getAuthenticatedUser();
        $profile = $this->taxProfileService->getById($authUser, $id);

        return $profile
            ? response()->json($profile, ResponseAlias::HTTP_OK)
            : $this->errorResponse('Tax Profile not found', ResponseAlias::HTTP_NOT_FOUND);
    }

    // POST /api/tax-profile
    public function store(Request $request): JsonResponse
    {
        try {
            $authUser = $this->getAuthenticatedUser();
            $profile = $this->taxProfileService->create($authUser, $request->all());

            return response()->json($profile, ResponseAlias::HTTP_CREATED);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => 'Forbidden'], ResponseAlias::HTTP_FORBIDDEN);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->errors()], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    // PUT /api/tax-profile/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $authUser = $this->getAuthenticatedUser();
            $profile = $this->taxProfileService->update($authUser, $id, $request->all());

            return $profile
                ? response()->json($profile, ResponseAlias::HTTP_OK)
                : $this->errorResponse('Tax Profile not found', ResponseAlias::HTTP_NOT_FOUND);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Forbidden', ResponseAlias::HTTP_FORBIDDEN);
        }
    }

    // DELETE /api/tax-profile/{id}
    public function destroy(int $id): JsonResponse
    {
        try {
            $authUser = $this->getAuthenticatedUser();

            return $this->taxProfileService->delete($authUser, $id)
                ? response()->json(['message' => 'Tax Profile deleted'], ResponseAlias::HTTP_OK)
                : $this->errorResponse('Tax Profile not found', ResponseAlias::HTTP_NOT_FOUND);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Forbidden', ResponseAlias::HTTP_FORBIDDEN);
        }
    }
}
