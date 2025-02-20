<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

abstract class Controller extends BaseController
{
    /**
     * Helper function for error responses.
     */
    protected function errorResponse(string $message, int $statusCode): JsonResponse
    {
        return response()->json(['message' => $message], $statusCode);
    }

    /**
     * Retrieve and validate the authenticated user.
     */
    protected function getAuthenticatedUser(): User
    {
        /** @var User|null $authUser */
        $authUser = auth()->user();

        if (!$authUser instanceof User) {
            Log::error('Unauthorized access attempt detected');
            abort(ResponseAlias::HTTP_UNAUTHORIZED, 'Unauthorized');
        }

        return $authUser;
    }
}
