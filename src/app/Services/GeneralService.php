<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

abstract class GeneralService
{
    /**
     * Ensure the user is an admin.
     */
    public function authorizeAdmin(?User $user): void
    {
        if (!$user || $user->role !== 'admin') {
            Log::error('Unauthorized admin access attempt', ['user_id' => $user?->id]);
            throw new AuthorizationException('Forbidden', 403);
        }
    }

    public function authorizeAdminOrOwner(User $authUser, ?int $resourceUserId = null): void
    {
        if ($authUser->role !== 'admin' && ($resourceUserId !== null && $authUser->id !== $resourceUserId)) {
            Log::error('Unauthorized access attempt', [
                'user_id' => $authUser->id,
                'resource_user_id' => $resourceUserId,
            ]);
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
            Log::error('Validation failed', ['errors' => $validator->errors()]);
            throw new ValidationException($validator);
        }

        // 2) Check for any extra keys that aren't in $rules
        $allowedKeys = array_keys($rules);
        $dataKeys = array_keys($data);
        $extraKeys = array_diff($dataKeys, $allowedKeys);

        if (!empty($extraKeys)) {
            Log::error('Unexpected fields in request', ['extra_fields' => $extraKeys]);
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
        try {
            $params = ['filters' => []];

            // Ensure non-admin users can only fetch their own tax profiles or invoices
            if ($userIdField && $authUser->role !== 'admin') {
                $params['filters'][] = [
                    'field'     => 'user_id',
                    'value'     => $authUser->id,
                    'fieldType' => 'number',
                    'operator'  => 'equals'
                ];
            }

            // Merge additional filters from request
            if (isset($requestData['filters']) && is_array($requestData['filters'])) {
                $params['filters'] = array_merge($params['filters'], $requestData['filters']);
            }

            // Handle sorting
            if (isset($requestData['sort']) && is_array($requestData['sort'])) {
                $params['sort_by'] = $requestData['sort']['field'] ?? null;
                $params['sort_dir'] = $requestData['sort']['direction'] ?? 'asc';
            }

            // Set limit
            if (isset($requestData['limit'])) {
                $params['limit'] = $requestData['limit'];
            }

            return $params;
        } catch (Throwable $e) {
            Log::error('Error preparing filters', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
