<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

abstract class TestCase extends BaseTestCase
{
    protected function setUpJwtAuth(): void
    {
        $this->seed(RolePermissionSeeder::class);
    }

    protected function jwtAs(User $user): array
    {
        $token = JWTAuth::fromUser($user);
        return ['Authorization' => 'Bearer ' . $token];
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
