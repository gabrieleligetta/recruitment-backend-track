<?php

namespace App\Repositories;

use App\Models\TaxProfile;
use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TaxProfileRepository implements RepositoryInterface
{
    public function all(array $filters = []): LengthAwarePaginator
    {
        $query = TaxProfile::query();

        if (isset($filters['company_name'])) {
            $query->where('company_name', 'like', '%' . $filters['company_name'] . '%');
        }
        if (isset($filters['tax_id'])) {
            $query->where('tax_id', 'like', '%' . $filters['tax_id'] . '%');
        }

        $limit = $filters['limit'] ?? 10;
        return $query->paginate($limit);
    }

    public function create(array $data): TaxProfile
    {
        return TaxProfile::create($data);
    }

    public function update($id, array $data): ?TaxProfile
    {
        $taxProfile = TaxProfile::find($id);
        if (!$taxProfile) {
            return null;
        }
        $taxProfile->update($data);
        return $taxProfile;
    }

    public function findById($id): ?TaxProfile
    {
        return TaxProfile::find($id);
    }

    public function delete($id): bool
    {
        $taxProfile = TaxProfile::find($id);
        if (!$taxProfile) {
            return false;
        }
        return $taxProfile->delete();
    }
}
