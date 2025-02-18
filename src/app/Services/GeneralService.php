<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

abstract class GeneralService
{
    /**
     * Ensure the user is an admin.
     */
    public function authorizeAdmin(?User $user): void
    {
        if (!$user || $user->role !== 'admin') {
            throw new AuthorizationException('Forbidden', 403);
        }
    }

    public function authorizeAdminOrOwner(User $authUser, ?int $resourceUserId = null): void
    {
        if ($authUser->role !== 'admin' && ($resourceUserId !== null && $authUser->id !== $resourceUserId)) {
            throw new AuthorizationException('Forbidden', 403);
        }
    }



    /**
     * Handles filtering, sorting, and pagination.
     */
    protected function prepareFilters(User $authUser, array $requestData): array
    {
        $params = ['filters' => []];

        // ✅ Ensure non-admin users can only fetch their own tax profiles
        if ($authUser->role !== 'admin') {
            $params['filters'][] = [
                'field'     => 'user_id',
                'value'     => $authUser->id,
                'fieldType' => 'number',
                'operator'  => 'equals'
            ];
        }

        // ✅ Merge additional filters from request
        if (isset($requestData['filters']) && is_array($requestData['filters'])) {
            $params['filters'] = array_merge($params['filters'], $requestData['filters']);
        }

        // ✅ Handle sorting
        if (isset($requestData['sort']) && is_array($requestData['sort'])) {
            $params['sort_by'] = $requestData['sort']['field'] ?? null;
            $params['sort_dir'] = $requestData['sort']['direction'] ?? 'asc';
        }

        // ✅ Set limit
        if (isset($requestData['limit'])) {
            $params['limit'] = $requestData['limit'];
        }

        return $params;
    }
}
