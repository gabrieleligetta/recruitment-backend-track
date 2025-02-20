<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Auth", description: "Authentication endpoints")]
#[OA\PathItem(path: "/api/auth")]
class AuthController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    #[OA\Post(
        path: "/api/auth/signup",
        description: "Register a new user",
        summary: "User Signup",
        requestBody: new OA\RequestBody(
            description: "User data for signup",
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "password"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "John Doe"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "secret")
                ]
            )
        ),
        tags: ["Auth"],
        responses: [
            new OA\Response(
                response: 201,
                description: "User created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "user", ref: "#/components/schemas/User", type: "object"),
                        new OA\Property(property: "token", type: "string", example: "JWT_TOKEN")
                    ]
                )
            )
        ]
    )]
    public function signup(Request $request): JsonResponse
    {
        Log::info('Signup request received', ['email' => $request->email]);

        try {
            $validatedData = $this->userService->validateSignup($request->all());
            $user = $this->userService->create($validatedData);
            $credentials = $request->only('email', 'password');
            $token = auth('api')->attempt($credentials);

            Log::info('User signed up successfully', ['user_id' => $user->id]);

            return response()->json([
                'user'  => $user,
                'token' => $token,
            ], 201);
        } catch (Exception $e) {
            Log::error('Signup failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Signup failed'], 500);
        }
    }

    #[OA\Post(
        path: "/api/auth/login",
        description: "Authenticate a user and return a JWT token",
        summary: "User Login",
        requestBody: new OA\RequestBody(
            description: "Credentials for login",
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "secret")
                ]
            )
        ),
        tags: ["Auth"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Login successful, returns JWT token",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "token", type: "string", example: "JWT_TOKEN")
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: "Invalid credentials",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "error", type: "string", example: "Invalid credentials")
                    ]
                )
            )
        ]
    )]
    public function login(Request $request): JsonResponse
    {
        Log::info('Login attempt', ['email' => $request->email]);

        $credentials = $request->only('email', 'password');

        if (!$token = auth('api')->attempt($credentials)) {
            Log::warning('Login failed due to invalid credentials', ['email' => $request->email]);
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        Log::info('User logged in successfully', ['email' => $request->email]);

        return response()->json(['token' => $token]);
    }

    #[OA\Get(
        path: "/api/auth/me",
        description: "Return the authenticated user's information",
        summary: "Get Authenticated User",
        security: [["bearerAuth" => []] ],
        tags: ["Auth"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Authenticated user information",
                content: new OA\JsonContent(ref: "#/components/schemas/User")
            )
        ]
    )]
    public function me(): JsonResponse
    {   /** @var User|null $user */
        $user = auth()->user();

        if (!$user) {
            Log::warning('Attempt to access /me without authentication');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        Log::info('User fetched profile', ['user_id' => $user->id]);

        return response()->json($user);
    }
}
