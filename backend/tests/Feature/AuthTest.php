<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'teacher@chamthi.com',
            'password' => Hash::make('secret123'),
            'role' => 'teacher',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'teacher@chamthi.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'email' => 'teacher@chamthi.com',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'teacher@chamthi.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => 'UNAUTHORIZED']);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@chamthi.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => 'UNAUTHORIZED']);
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Đã đăng xuất.']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }

    public function test_get_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJsonPath('email', $user->email);
    }
}
