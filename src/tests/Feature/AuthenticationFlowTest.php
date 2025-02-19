<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_allows_a_user_to_signup_login_and_get_their_info(): void
    {
        // 1️⃣ STEP 1: Register a New User
        $signupResponse = $this->postJson('/api/auth/signup', [
            'name'     => 'John Doe',
            'email'    => 'john.doe@example.com',
            'password' => 'securepassword123',
        ]);

        // Ensure signup is successful (201 Created)
        $signupResponse->assertStatus(201);
        $signupResponse->assertJsonStructure([
            'user' => [
                'id',
                'name',
                'email',
            ],
            'token'
        ]);

        // Extract token from the signup response
        $signupResponse->json('token');

        // Ensure user exists in the database
        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
        ]);

        // 2️⃣ STEP 2: Login with the same user
        $loginResponse = $this->postJson('/api/auth/login', [
            'email'    => 'john.doe@example.com',
            'password' => 'securepassword123',
        ]);

        // Ensure login is successful (200 OK) and a token is returned
        $loginResponse->assertStatus(200);
        $loginResponse->assertJsonStructure([
            'token'
        ]);

        // Extract token from login response
        $loginToken = $loginResponse->json('token');

        // 3️⃣ STEP 3: Use the token to get user info (GET /api/auth/me)
        $meResponse = $this->withHeader('Authorization', "Bearer $loginToken")
            ->getJson('/api/auth/me');

        // Ensure the request is successful and returns user data
        $meResponse->assertStatus(200);
        $meResponse->assertJson([
            'id'    => $signupResponse->json('user.id'),
            'name'  => 'John Doe',
            'email' => 'john.doe@example.com',
        ]);
    }

    #[Test]
    public function it_denies_access_to_me_endpoint_without_a_token(): void
    {
        $response = $this->getJson('/api/auth/me');

        // Ensure unauthenticated users are denied access (401 Unauthorized)
        $response->assertStatus(401);
    }
}
