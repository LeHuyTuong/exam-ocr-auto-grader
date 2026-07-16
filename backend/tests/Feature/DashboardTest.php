<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private SchoolClass $class;
    private Exam $exam;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwtAuth();
        $this->user = $this->jwtAsTeacher();
        $this->class = SchoolClass::factory()->create();
        $this->user->classes()->attach($this->class->id);
        $this->exam = Exam::factory()->create([
            'class_id' => $this->class->id,
        ]);
        $this->student = Student::factory()->create(['class_id' => $this->class->id]);
        Grade::factory()->count(3)->create([
            'exam_id' => $this->exam->id,
            'class_id' => $this->class->id,
            'student_id' => $this->student->id,
            'score' => 8.0,
        ]);
    }

    public function test_class_stats(): void
    {
        $response = $this->withHeaders($this->jwtAs($this->user))
            ->getJson('/api/dashboard/class/'.$this->class->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'class' => ['id', 'code', 'name', 'level'],
                'stats' => [
                    'total_students', 'total_exams', 'total_grades',
                    'average_score', 'highest_score', 'lowest_score',
                ],
            ])
            ->assertJsonPath('stats.total_grades', 3)
            ->assertJsonPath('stats.average_score', 8);
    }

    public function test_student_stats(): void
    {
        $response = $this->withHeaders($this->jwtAs($this->user))
            ->getJson('/api/dashboard/class/'.$this->class->id.'/students');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'students' => [
                    '*' => ['id', 'full_name', 'total_exams', 'average_score', 'best_score', 'worst_score'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('students.0.total_exams', 3)
            ->assertJsonPath('students.0.average_score', 8);
    }

    public function test_unauthorized_user_cannot_access_dashboard(): void
    {
        $response = $this->getJson('/api/dashboard/class/'.$this->class->id);
        $response->assertStatus(401);
    }

    public function test_forbidden_user_cannot_access_other_class(): void
    {
        $otherUser = $this->jwtAsTeacher();
        $otherClass = SchoolClass::factory()->create();
        $otherUser->classes()->attach($otherClass->id);
        Student::factory()->create(['class_id' => $otherClass->id]);

        $response = $this->withHeaders($this->jwtAs($this->user))
            ->getJson('/api/dashboard/class/'.$otherClass->id);

        $response->assertStatus(403);
    }
}
