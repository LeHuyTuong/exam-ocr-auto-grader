<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUpJwtAuth(): void
    {
        $this->seed(RolePermissionSeeder::class);
    }

    protected function sanctumToken(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    protected function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $this->sanctumToken($user)];
    }

    protected function jwtAs(User $user): array
    {
        return $this->authHeader($user);
    }

    protected function jwtAsTeacher(array $data = []): User
    {
        $user = User::factory()->create($data);
        $user->assignRole('teacher');
        return $user;
    }

    protected function jwtAsAdmin(array $data = []): User
    {
        $user = User::factory()->create($data);
        $user->assignRole('admin');
        return $user;
    }
}
