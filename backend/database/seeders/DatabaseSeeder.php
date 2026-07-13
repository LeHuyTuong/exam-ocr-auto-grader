<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@chamthi.com',
            'password' => bcrypt('admin123'),
            'role' => 'admin',
        ]);

        $teacher = User::factory()->create([
            'name' => 'Cô Giáo A',
            'email' => 'coa@chamthi.com',
            'password' => bcrypt('teacher123'),
            'role' => 'teacher',
        ]);

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
            'date' => now()->toDateString(),
            'total_questions' => 50,
            'max_score' => 10,
            'created_by' => $teacher->id,
        ]);

        $this->command->info('Seeded: admin (admin@chamthi.com / admin123)');
        $this->command->info('Seeded: teacher (coa@chamthi.com / teacher123)');
        $this->command->info('Seeded: class TA-101 with 5 students + 1 exam today');
    }
}
