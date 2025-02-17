<?php

namespace App\Services;

use App\Models\TaxProfile;
use App\Repositories\TaxProfileRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TaxProfileService
{
    protected TaxProfileRepository $taxProfileRepository;

    public function __construct(TaxProfileRepository $taxProfileRepository)
    {
        $this->taxProfileRepository = $taxProfileRepository;
    }

    /**
     * Get a paginated list of tax profiles.
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        return $this->taxProfileRepository->all($filters);
    }

    /**
     * Find a TaxProfile by its ID.
     *
     * @param mixed $id
     * @return TaxProfile|null
     */
    public function getById($id): ?TaxProfile
    {
        return $this->taxProfileRepository->findById($id);
    }

    /**
     * Create a new TaxProfile.
     *
     * @param array $data
     * @return TaxProfile
     */
    public function create(array $data): TaxProfile
    {
        return $this->taxProfileRepository->create($data);
    }

    /**
     * Update an existing TaxProfile.
     *
     * @param mixed $id
     * @param array $data
     * @return TaxProfile|null
     */
    public function update($id, array $data): ?TaxProfile
    {
        return $this->taxProfileRepository->update($id, $data);
    }

    /**
     * Delete a TaxProfile.
     *
     * @param mixed $id
     * @return bool
     */
    public function delete($id): bool
    {
        return $this->taxProfileRepository->delete($id);
    }
}
