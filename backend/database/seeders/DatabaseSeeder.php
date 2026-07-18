<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
        ]);

        // Security: credentials từ env vars, KHÔNG hardcode.
        // Trên production, set qua server env hoặc GitHub Secrets.
        // Local dev: copy .env.example và tự điền giá trị.

        $adminEmail = env('SEED_ADMIN_EMAIL', '');
        $adminPassword = env('SEED_ADMIN_PASSWORD', '');
        $teacherEmail = env('SEED_TEACHER_EMAIL', '');
        $teacherPassword = env('SEED_TEACHER_PASSWORD', '');

        if ($adminEmail && $adminPassword) {
            $admin = User::factory()->create([
                'name' => 'Admin',
                'email' => $adminEmail,
                'password' => Hash::make($adminPassword),
            ]);
            $admin->assignRole('admin');
            $this->command->info("Seeded admin: {$adminEmail}");
        } else {
            $this->command->warn('Skipping admin seed: set SEED_ADMIN_EMAIL and SEED_ADMIN_PASSWORD in .env');
        }

        if ($teacherEmail && $teacherPassword) {
            $teacher = User::factory()->create([
                'name' => 'Teacher',
                'email' => $teacherEmail,
                'password' => Hash::make($teacherPassword),
            ]);
            $teacher->assignRole('teacher');

            $class = SchoolClass::create([
                'code' => 'TA-101',
                'name' => 'Tiếng Anh 101',
                'level' => 'primary',
            ]);

            $class->teachers()->attach($teacher->id);

            $students = [
                ['full_name' => 'Nguyễn Văn An', 'normalized_name' => 'nguyen van an'],
                ['full_name' => 'Trần Thị Bích', 'normalized_name' => 'tran thi bich'],
                ['full_name' => 'Lê Hoàng Cường', 'normalized_name' => 'le hoang cuong'],
                ['full_name' => 'Phạm Minh Dung', 'normalized_name' => 'pham minh dung'],
                ['full_name' => 'Đỗ Thị Hoa', 'normalized_name' => 'do thi hoa'],
            ];

            foreach ($students as $s) {
                Student::create([
                    'class_id' => $class->id,
                    'full_name' => $s['full_name'],
                    'normalized_name' => $s['normalized_name'],
                    'aliases' => [],
                ]);
            }

            $exam = Exam::create([
                'class_id' => $class->id,
                'name' => 'Kiểm tra ngày '.now()->format('d/m/Y'),
                'total_questions' => 50,
                'max_score' => 10,
                'grading_mode' => 'counting',
                'created_by' => $teacher->id,
            ]);

            $this->command->info("Seeded teacher: {$teacherEmail}");
            $this->command->info('Seeded: class TA-101 with 5 students + 1 exam today');
        } else {
            $this->command->warn('Skipping teacher seed: set SEED_TEACHER_EMAIL and SEED_TEACHER_PASSWORD in .env');
        }
    }
}
