<?php

namespace App\Services;

use App\Models\TaxProfile;
use App\Models\User;
use App\Repositories\TaxProfileRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpFoundation\Response as HTTPCode;
use Illuminate\Support\Facades\Log;
use Throwable;

class TaxProfileService extends GeneralService
{
    protected TaxProfileRepository $taxProfileRepository;

    public function __construct(TaxProfileRepository $taxProfileRepository)
    {
        $this->taxProfileRepository = $taxProfileRepository;
    }

    /**
     * Retrieve tax profiles based on filters.
     */
    public function getAll(User $authUser, array $requestData = []): LengthAwarePaginator
    {
        try {
            $filters = $this->prepareFilters($authUser, $requestData);
            return $this->taxProfileRepository->all($filters);
        } catch (Throwable $e) {
            Log::error('Error fetching tax profiles', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create a new Tax Profile.
     */
    public function create(User $authUser, array $data): TaxProfile
    {
        try {
            $this->authorizeAdminOrOwner($authUser, $authUser->id);

            // Ensure non-admin users can only create their own tax profile
            if ($authUser->role !== 'admin') {
                $data['user_id'] = $authUser->id;
            }

            $validatedData = $this->validateProfile($data, false);
            return $this->taxProfileRepository->create($validatedData);
        } catch (Throwable $e) {
            Log::error('Error creating tax profile', ['user_id' => $authUser->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Validate tax profile input.
     */
    private function validateProfile(array $data, bool $isUpdate = false, ?int $id = null): array
    {
        try {
            $rules = [
                'user_id'      => $isUpdate ? "sometimes|required|exists:users,id" : "required|exists:users,id",
                'tax_id'       => $isUpdate ? "sometimes|required|string|unique:tax_profiles,tax_id,{$id}" : 'required|string|unique:tax_profiles,tax_id',
                'company_name' => $isUpdate ? 'sometimes|required|string|max:255' : 'required|string|max:255',
                'address'      => $isUpdate ? 'sometimes|required|string' : 'required|string',
                'country'      => $isUpdate ? 'sometimes|required|string|max:100' : 'required|string|max:100',
                'city'         => $isUpdate ? 'sometimes|required|string|max:100' : 'required|string|max:100',
                'zip_code'     => $isUpdate ? 'sometimes|required|string|max:20' : 'required|string|max:20',
            ];

            return $this->generalValidation($data, $rules);
        } catch (Throwable $e) {
            Log::error('Tax profile validation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Update an existing TaxProfile.
     */
    public function update(User $authUser, int $id, array $data): ?TaxProfile
    {
        try {
            $profile = $this->getById($authUser, $id);
            if (!$profile) return null;

            $this->authorizeAdminOrOwner($authUser, $profile->user_id);
            $validatedData = $this->validateProfile($data, true, $id);
            return $this->taxProfileRepository->update($id, $validatedData);
        } catch (Throwable $e) {
            Log::error('Error updating tax profile', ['tax_profile_id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Find a TaxProfile by its ID.
     */
    public function getById(User $authUser, int $id): ?TaxProfile
    {
        try {
            $profile = $this->taxProfileRepository->findById($id);
            if (!$profile) {
                return null;
            }

            // Ensure only admins or the owner can access the profile
            $this->authorizeAdminOrOwner($authUser, $profile->user_id);
            return $profile;
        } catch (AuthorizationException $e) {
            Log::error('Unauthorized access to tax profile', ['tax_profile_id' => $id, 'user_id' => $authUser->id, 'error' => $e->getMessage()]);
            throw new AuthorizationException('Forbidden', HTTPCode::HTTP_FORBIDDEN);
        } catch (Throwable $e) {
            Log::error('Error fetching tax profile', ['tax_profile_id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Delete a TaxProfile.
     */
    public function delete(User $authUser, int $id): bool
    {
        try {
            $profile = $this->getById($authUser, $id);
            if (!$profile) return false;

            $this->authorizeAdminOrOwner($authUser, $profile->user_id);
            return $this->taxProfileRepository->delete($id);
        } catch (Throwable $e) {
            Log::error('Error deleting tax profile', ['tax_profile_id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
