<?php

namespace App\Services;

use App\Models\TaxProfile;
use App\Models\User;
use App\Repositories\TaxProfileRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Illuminate\Auth\Access\AuthorizationException;

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
        $filters = $this->prepareFilters($authUser, $requestData);
        return $this->taxProfileRepository->all($filters);
    }

    public function create(User $authUser, array $data): TaxProfile
    {
        $this->authorizeAdminOrOwner($authUser, $authUser->id);

        // ✅ Ensure non-admin users can only create their own tax profile
        if ($authUser->role !== 'admin') {
            $data['user_id'] = $authUser->id; // ✅ Set user_id if not provided
        }

        $validatedData = $this->validateProfile($data, false);

        return $this->taxProfileRepository->create($validatedData);
    }


    /**
     * Validate tax profile input.
     */
    private function validateProfile(array $data, bool $isUpdate = false, ?int $id = null): array
    {
        $rules = [
            'user_id'      => $isUpdate ? 'sometimes|required|exists:users,id' : 'required|exists:users,id',
            'tax_id'       => $isUpdate ? "sometimes|required|string|unique:tax_profiles,tax_id,{$id}" : 'required|string|unique:tax_profiles,tax_id',
            'company_name' => $isUpdate ? 'sometimes|required|string|max:255' : 'required|string|max:255',
            'address'      => $isUpdate ? 'sometimes|required|string' : 'required|string',
            'country'      => $isUpdate ? 'sometimes|required|string|max:100' : 'required|string|max:100',
            'city'         => $isUpdate ? 'sometimes|required|string|max:100' : 'required|string|max:100',
            'zip_code'     => $isUpdate ? 'sometimes|required|string|max:20' : 'required|string|max:20',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }



    /**
     * Update an existing TaxProfile.
     */
    public function update(User $authUser, int $id, array $data): ?TaxProfile
    {
        $profile = $this->getById($authUser,$id);
        if (!$profile) return null;

        $this->authorizeAdminOrOwner($authUser, $profile->user_id);

        $validatedData = $this->validateProfile($data, true, $id);
        return $this->taxProfileRepository->update($id, $validatedData);
    }

    /**
     * Find a TaxProfile by its ID.
     */
    public function getById(User $authUser, int $id): ?TaxProfile
    {
        $profile = $this->taxProfileRepository->findById($id);

        if (!$profile) {
            return null;
        }

        try {
            // ✅ Ensure only admins or the owner can access the profile
            $this->authorizeAdminOrOwner($authUser, $profile->user_id);
        } catch (AuthorizationException $e) {
            throw new AuthorizationException('Forbidden', 403);
        }

        return $profile;
    }



    /**
     * Delete a TaxProfile.
     */
    public function delete(User $authUser, int $id,): bool
    {
        $profile = $this->getById($authUser, $id);
        if (!$profile) return false;

        $this->authorizeAdminOrOwner($authUser, $profile->user_id);
        return $this->taxProfileRepository->delete($id);
    }
}
