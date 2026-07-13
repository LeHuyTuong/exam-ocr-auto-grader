<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'class.view',
            'exam.view',
            'exam.create',
            'exam.edit',
            'exam.delete',
            'question.manage',
            'submission.create',
            'submission.manage',
            'answer.manage',
            'grade.view',
            'grade.create',
            'ocr.use',
            'dashboard.view',
            'user.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        $teacherRole = Role::create(['name' => 'teacher']);
        $teacherRole->givePermissionTo([
            'class.view',
            'exam.view',
            'exam.create',
            'exam.edit',
            'question.manage',
            'submission.create',
            'submission.manage',
            'answer.manage',
            'grade.view',
            'grade.create',
            'ocr.use',
            'dashboard.view',
        ]);

        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all());
    }
}
