<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;


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
     * Handles general Validation.
     */
    protected function generalValidation(array $data, array $rules): array
    {
        // 1) Validate using the defined $rules
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // 2) Check for any extra keys that aren't in $rules
        $allowedKeys = array_keys($rules); // e.g. ['user_id', 'tax_id', 'company_name', ...]
        $dataKeys    = array_keys($data);  // keys the client actually sent
        $extraKeys   = array_diff($dataKeys, $allowedKeys);

        if (!empty($extraKeys)) {
            // You can throw a ValidationException or a different exception type
            throw new ValidationException($validator, "These fields are not allowed: " . implode(', ', $extraKeys));
        }

        // 3) Return only the validated data
        return $validator->validated();
    }

    /**
     * Handles filtering, sorting, and pagination.
     */
    protected function prepareFilters(User $authUser, array $requestData, $userIdField = true): array
    {
        $params = ['filters' => []];

        //Ensure non-admin users can only fetch their own tax profiles or invoices if the field user_id is present
        if ($userIdField) {
            if ($authUser->role !== 'admin') {
                $params['filters'][] = [
                    'field'     => 'user_id',
                    'value'     => $authUser->id,
                    'fieldType' => 'number',
                    'operator'  => 'equals'
                ];
            }
        }

        //Merge additional filters from request
        if (isset($requestData['filters']) && is_array($requestData['filters'])) {
            $params['filters'] = array_merge($params['filters'], $requestData['filters']);
        }

        //Handle sorting
        if (isset($requestData['sort']) && is_array($requestData['sort'])) {
            $params['sort_by'] = $requestData['sort']['field'] ?? null;
            $params['sort_dir'] = $requestData['sort']['direction'] ?? 'asc';
        }

        //Set limit
        if (isset($requestData['limit'])) {
            $params['limit'] = $requestData['limit'];
        }

        return $params;
    }
}
