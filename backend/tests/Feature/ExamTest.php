<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_get_today_exam_returns_404_when_no_exam(): void
    {
        $class = SchoolClass::factory()->create();
        $this->user->classes()->attach($class->id);

        $response = $this->actingAs($this->user)
            ->getJson('/api/exams/today?class_id='.$class->id);

        $response->assertStatus(404)
            ->assertJson(['error' => 'NOT_FOUND']);
    }

    public function test_get_today_exam_returns_existing_exam(): void
    {
        $exam = Exam::factory()->create(['date' => today()]);
        $this->user->classes()->attach($exam->class_id);

        $response = $this->actingAs($this->user)
            ->getJson('/api/exams/today?class_id='.$exam->class_id);

        $response->assertStatus(200)
            ->assertJsonStructure(['exam' => ['id', 'name', 'date', 'totalQuestions', 'maxScore']]);
    }

    public function test_create_today_exam_successfully(): void
    {
        $class = SchoolClass::factory()->create();
        $this->user->classes()->attach($class->id);

        $response = $this->actingAs($this->user)
            ->postJson('/api/exams/today', [
                'class_id' => $class->id,
                'total_questions' => 50,
                'max_score' => 10,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['exam' => ['id', 'name', 'totalQuestions', 'maxScore']]);

        $this->assertDatabaseHas('exams', [
            'class_id' => $class->id,
            'total_questions' => 50,
            'max_score' => 10,
        ]);
    }

    public function test_create_today_exam_returns_existing_if_already_exists(): void
    {
        $exam = Exam::factory()->create(['date' => today()]);
        $this->user->classes()->attach($exam->class_id);

        $response = $this->actingAs($this->user)
            ->postJson('/api/exams/today', [
                'class_id' => $exam->class_id,
                'total_questions' => 99,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('exams', ['id' => $exam->id, 'total_questions' => $exam->total_questions]);
        $this->assertDatabaseMissing('exams', ['total_questions' => 99]);
    }

    public function test_create_exam_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/exams/today', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['class_id', 'total_questions']);
    }

    public function test_unauthenticated_user_cannot_access_exams(): void
    {
        $response = $this->getJson('/api/exams/today');
        $response->assertStatus(401);
    }
}
