<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Models\Yle\YleExam;
use App\Models\Yle\YlePart;
use App\Models\Yle\YleQuestion;
use App\Models\Yle\YleSubmission;
use App\Support\YleTemplates;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder trình diễn (KHÔNG chạy trên production) — dựng một bộ dữ liệu
 * phong phú, nhiều tháng lịch sử điểm, để dashboard/chart/export có gì đó
 * đẹp mà xem khi demo hệ thống trên máy local.
 *
 * Chạy: php artisan db:seed --class=Database\\Seeders\\DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
        ]);

        $admin = User::factory()->create([
            'name' => 'Admin Demo',
            'email' => 'admin@demo.local',
            'password' => Hash::make('Password123!'),
        ]);
        $admin->assignRole('admin');
        $this->command->info('Admin: admin@demo.local / Password123!');

        $teachers = [
            ['name' => 'Cô Nguyễn Thu Hà', 'email' => 'teacher1@demo.local'],
            ['name' => 'Thầy Trần Minh Khoa', 'email' => 'teacher2@demo.local'],
        ];

        $teacherUsers = [];
        foreach ($teachers as $t) {
            $user = User::factory()->create([
                'name' => $t['name'],
                'email' => $t['email'],
                'password' => Hash::make('Password123!'),
            ]);
            $user->assignRole('teacher');
            $teacherUsers[] = $user;
        }
        $this->command->info('Teacher: teacher1@demo.local / Password123! (và teacher2@demo.local)');

        $classesData = [
            [
                'code' => 'STARTERS-A',
                'name' => 'Starters A - Sáng T2/4/6',
                'level' => 'primary',
                'teacher' => $teacherUsers[0],
                'students' => [
                    'Nguyễn Văn An', 'Trần Thị Bích', 'Lê Hoàng Cường', 'Phạm Minh Dung',
                    'Đỗ Thị Hoa', 'Vũ Đức Huy', 'Bùi Thị Lan', 'Ngô Quốc Bảo',
                ],
            ],
            [
                'code' => 'MOVERS-B',
                'name' => 'Movers B - Chiều T3/5/7',
                'level' => 'primary',
                'teacher' => $teacherUsers[0],
                'students' => [
                    'Hoàng Gia Bảo', 'Đặng Thị Mai', 'Phan Văn Nam', 'Lý Thị Oanh',
                    'Trương Đức Phát', 'Đinh Thị Quỳnh', 'Lâm Văn Sơn',
                ],
            ],
            [
                'code' => 'FLYERS-C',
                'name' => 'Flyers C - Tối T2/4/6',
                'level' => 'secondary',
                'teacher' => $teacherUsers[1],
                'students' => [
                    'Nguyễn Thị Thắm', 'Trần Văn Tùng', 'Lê Thị Uyên', 'Phạm Văn Việt',
                    'Hồ Thị Xuân', 'Mai Văn Yên',
                ],
            ],
        ];

        // 6 kỳ kiểm tra gần đây, mỗi kỳ cách nhau ~2 tuần, kỳ cuối = "hôm nay".
        $examDates = collect(range(5, 0))->map(fn ($weeksAgo) => now()->subWeeks($weeksAgo * 2));

        foreach ($classesData as $classData) {
            $class = SchoolClass::create([
                'code' => $classData['code'],
                'name' => $classData['name'],
                'level' => $classData['level'],
            ]);
            $class->teachers()->attach($classData['teacher']->id);

            $students = collect($classData['students'])->map(function (string $name) use ($class) {
                $normalized = \Illuminate\Support\Str::of($name)->lower()->ascii()->toString();

                return Student::create([
                    'class_id' => $class->id,
                    'full_name' => $name,
                    'normalized_name' => $normalized,
                    'aliases' => [],
                ]);
            });

            // Điểm khởi điểm ngẫu nhiên mỗi học sinh, có xu hướng tăng dần qua các kỳ
            // (một vài em cố tình yếu đều ở 1-2 kỹ năng để bảng "học sinh cần hỗ trợ" có dữ liệu).
            $baselines = $students->mapWithKeys(fn ($s) => [$s->id => [
                'vocabulary' => rand(6, 8),
                'grammar' => rand(6, 8),
                'listening' => rand(6, 8),
                'reading' => rand(3, 4),
                'writing' => rand(3, 4),
                'speaking' => rand(6, 8),
            ]]);

            $weakStudentIds = $students->random(min(2, $students->count()))->pluck('id');

            foreach ($examDates as $i => $examDate) {
                $isLast = $i === $examDates->count() - 1;

                $exam = Exam::create([
                    'class_id' => $class->id,
                    'name' => 'Unit Test '.($i + 1).' - '.$examDate->format('d/m/Y'),
                    'total_questions' => 50,
                    'max_score' => 50,
                    'grading_mode' => 'graded',
                    'created_by' => $classData['teacher']->id,
                    'is_active' => $isLast,
                    'created_at' => $examDate,
                    'updated_at' => $examDate,
                ]);

                foreach ($students as $student) {
                    $base = $baselines[$student->id];
                    $isWeak = $weakStudentIds->contains($student->id);
                    $progress = $i; // điểm nhích dần theo từng kỳ

                    $sub = [
                        'vocabulary' => min(10, max(0, $base['vocabulary'] + intdiv($progress, 2) + rand(-1, 1))),
                        'grammar' => min(10, max(0, $base['grammar'] + intdiv($progress, 2) + rand(-1, 1))),
                        'listening' => min(10, max(0, $base['listening'] + intdiv($progress, 2) + rand(-1, 1))),
                        'reading' => min(5, max(0, $base['reading'] + intdiv($progress, 3) + rand(-1, 1))),
                        'writing' => min(5, max(0, $base['writing'] + intdiv($progress, 3) + rand(-1, 1))),
                        'speaking' => min(10, max(0, $base['speaking'] + intdiv($progress, 2) + rand(-1, 1))),
                    ];

                    if ($isWeak) {
                        // Giữ 1-2 kỹ năng dưới ngưỡng suốt để test bảng "cần hỗ trợ".
                        $sub['reading'] = rand(1, 3);
                        $sub['writing'] = rand(1, 3);
                    }

                    $total = array_sum($sub);

                    Grade::create([
                        'exam_id' => $exam->id,
                        'student_id' => $student->id,
                        'class_id' => $class->id,
                        'total_correct' => $total,
                        'score' => $total,
                        'sub_scores' => $sub,
                        'ai_confidence' => round(rand(80, 99) / 100, 2),
                        'status' => 'confirmed',
                        'confirmed_by' => $classData['teacher']->id,
                        'created_at' => $examDate,
                        'updated_at' => $examDate,
                    ]);
                }
            }

            $this->command->info("Lớp {$class->code}: {$students->count()} học sinh, ".$examDates->count().' kỳ kiểm tra đã chấm.');
        }

        // ---- YLE Cambridge demo: 1 đề Starters Listening + submissions mẫu ----
        $yleTemplate = YleTemplates::get('starters', 'listening');
        if ($yleTemplate) {
            $yleExam = YleExam::create([
                'level' => 'starters',
                'skill' => 'listening',
                'name' => $yleTemplate['name'],
                'total_marks' => $yleTemplate['total_marks'],
                'total_pages' => $yleTemplate['total_pages'],
                'created_by' => $admin->id,
            ]);

            foreach ($yleTemplate['parts'] as $partData) {
                $questions = $partData['questions'];
                unset($partData['questions']);

                $part = YlePart::create(array_merge($partData, [
                    'yle_exam_id' => $yleExam->id,
                ]));

                foreach ($questions as $qData) {
                    YleQuestion::create(array_merge($qData, [
                        'yle_part_id' => $part->id,
                        'correct_answer' => $qData['prompt'] ? null : 'sample',
                    ]));
                }
            }

            $firstClass = SchoolClass::first();
            $firstClassStudents = $firstClass?->students()->limit(3)->get() ?? collect();

            $statuses = ['completed', 'needs_review', 'auto_graded'];
            foreach ($firstClassStudents as $i => $student) {
                YleSubmission::create([
                    'yle_exam_id' => $yleExam->id,
                    'class_id' => $firstClass->id,
                    'student_id' => $student->id,
                    'exam_date' => now()->subDays($i),
                    'auto_score' => 15 - $i,
                    'manual_score' => 0,
                    'total_score' => 15 - $i,
                    'max_score' => $yleTemplate['total_marks'],
                    'status' => $statuses[$i % count($statuses)],
                    'created_by' => $teacherUsers[0]->id,
                ]);
            }

            $this->command->info("YLE demo: {$yleExam->name} + {$firstClassStudents->count()} submissions mẫu.");
        }

        $this->command->info('---');
        $this->command->info('Demo seed hoàn tất. Đăng nhập Filament (/admin) với admin@demo.local / Password123!');
    }
}
