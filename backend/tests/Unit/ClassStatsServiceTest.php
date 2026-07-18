<?php

namespace Tests\Unit;

use App\Models\Exam;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\ClassStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_trend_percent_compares_latest_vs_previous_grading_session(): void
    {
        // Mô hình: 1 lớp = 1 exam liên tục; mỗi lần chấm = 1 grade (theo created_at).
        $class = SchoolClass::factory()->create();
        $student = Student::factory()->create(['class_id' => $class->id]);
        $exam = Exam::factory()->create(['class_id' => $class->id]);

        // Đợt chấm cũ: điểm 6.0
        Grade::factory()->create([
            'exam_id' => $exam->id, 'class_id' => $class->id,
            'student_id' => $student->id, 'score' => 6.0,
            'created_at' => now()->subDays(10),
        ]);
        // Đợt chấm mới: điểm 9.0
        Grade::factory()->create([
            'exam_id' => $exam->id, 'class_id' => $class->id,
            'student_id' => $student->id, 'score' => 9.0,
            'created_at' => now()->subDays(2),
        ]);

        $svc = new ClassStatsService($class);

        // 6.0 -> 9.0: (9-6)/6 * 100 = +50%
        $this->assertSame(50.0, $svc->trendPercent());
        $this->assertSame(9.0, $svc->latestAverage());
    }

    public function test_trend_percent_null_when_only_one_grading_session(): void
    {
        $class = SchoolClass::factory()->create();
        $student = Student::factory()->create(['class_id' => $class->id]);
        $exam = Exam::factory()->create(['class_id' => $class->id]);
        Grade::factory()->create([
            'exam_id' => $exam->id, 'class_id' => $class->id,
            'student_id' => $student->id, 'score' => 8.0,
        ]);

        $svc = new ClassStatsService($class);

        $this->assertNull($svc->trendPercent());
        $this->assertSame(8.0, $svc->latestAverage());
    }

    public function test_negative_trend_when_score_drops(): void
    {
        $class = SchoolClass::factory()->create();
        $student = Student::factory()->create(['class_id' => $class->id]);
        $exam = Exam::factory()->create(['class_id' => $class->id]);

        Grade::factory()->create([
            'exam_id' => $exam->id, 'class_id' => $class->id,
            'student_id' => $student->id, 'score' => 10.0,
            'created_at' => now()->subDays(10),
        ]);
        Grade::factory()->create([
            'exam_id' => $exam->id, 'class_id' => $class->id,
            'student_id' => $student->id, 'score' => 8.0,
            'created_at' => now(),
        ]);

        // 10 -> 8: (8-10)/10 * 100 = -20%
        $this->assertSame(-20.0, (new ClassStatsService($class))->trendPercent());
    }

    public function test_weak_students_lists_those_with_skill_below_threshold(): void
    {
        $class = SchoolClass::factory()->create();
        $good = Student::factory()->create(['class_id' => $class->id, 'full_name' => 'Good']);
        $weak = Student::factory()->create(['class_id' => $class->id, 'full_name' => 'Weak']);
        $exam = Exam::factory()->create(['class_id' => $class->id, 'grading_mode' => 'graded']);

        Grade::factory()->create([
            'exam_id' => $exam->id, 'class_id' => $class->id, 'student_id' => $good->id,
            'score' => 48,
            'sub_scores' => ['vocabulary' => 10, 'grammar' => 9, 'listening' => 9, 'reading' => 5, 'writing' => 4, 'speaking' => 10],
        ]);
        Grade::factory()->create([
            'exam_id' => $exam->id, 'class_id' => $class->id, 'student_id' => $weak->id,
            'score' => 30,
            'sub_scores' => ['vocabulary' => 5, 'grammar' => 9, 'listening' => 9, 'reading' => 5, 'writing' => 4, 'speaking' => 9],
        ]);

        $weakStudents = (new ClassStatsService($class))->weakStudents();

        $this->assertCount(1, $weakStudents);
        $this->assertSame('Weak', $weakStudents[0]['full_name']);
        $this->assertContains('vocabulary', $weakStudents[0]['weak_skills']);
        $this->assertContains('Từ vựng', $weakStudents[0]['weak_skills_labels']);
    }

    public function test_skill_averages_flags_weak_skills_of_class(): void
    {
        $class = SchoolClass::factory()->create();
        $student = Student::factory()->create(['class_id' => $class->id]);
        $exam = Exam::factory()->create(['class_id' => $class->id, 'grading_mode' => 'graded']);

        // Lớp TB nghe = 5 (<9) -> yếu
        Grade::factory()->create([
            'exam_id' => $exam->id, 'class_id' => $class->id, 'student_id' => $student->id,
            'sub_scores' => ['listening' => 5],
        ]);

        $skills = (new ClassStatsService($class))->skillAverages();

        $this->assertTrue($skills['listening']['weak']);
        $this->assertFalse($skills['vocabulary']['weak']); // chưa có data -> không yếu
    }
}
