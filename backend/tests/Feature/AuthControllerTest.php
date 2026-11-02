<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// AC: T-01, T-02, T-03 — Sanctum auth, register/login/logout, token protection
class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    // T-02: register creates user and returns token
    public function test_register_creates_user_and_returns_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.user.email', 'jane@example.com');
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    // T-02: register rejects duplicate email
    public function test_register_rejects_duplicate_email(): void
    {
        User::create(['name' => 'Existing', 'email' => 'dup@example.com', 'password' => bcrypt('secret1234')]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'New',
            'email' => 'dup@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    // T-02: register rejects short password
    public function test_register_rejects_password_under_8_chars(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    // T-02: login with valid credentials returns token
    public function test_login_with_valid_credentials_returns_token(): void
    {
        User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'password' => bcrypt('password123')]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'alice@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data.token'));
    }

    // T-02: login with wrong password returns 401
    public function test_login_with_wrong_password_returns_401(): void
    {
        User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'password' => bcrypt('correct')]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'alice@example.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(401);
    }

    // T-03: protected endpoint rejects unauthenticated requests
    public function test_protected_endpoint_rejects_unauthenticated_request(): void
    {
        $response = $this->getJson('/api/dashboard/kpis');

        $response->assertStatus(401);
    }

    // T-02: logout invalidates the token
    public function test_logout_invalidates_token(): void
    {
        $user = User::create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => bcrypt('pass1234')]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])->postJson('/api/auth/logout')
            ->assertStatus(200);

        // Token is now revoked — protected endpoint should refuse it
        $this->withHeaders(['Authorization' => "Bearer {$token}"])->getJson('/api/dashboard/kpis')
            ->assertStatus(401);
    }

    // T-06: health endpoint is public (no auth required)
    public function test_health_endpoint_is_public(): void
    {
        $this->getJson('/api/health')->assertStatus(200)->assertJsonPath('status', 'ok');
    }
}
