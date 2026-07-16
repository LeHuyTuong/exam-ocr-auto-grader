<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeTest extends TestCase
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
            'total_questions' => 50,
            'max_score' => 10,
        ]);
        $this->student = Student::factory()->create([
            'class_id' => $this->class->id,
            'full_name' => 'Nguyen Van A',
        ]);
    }

    public function test_store_grade_successfully(): void
    {
        $response = $this->withHeaders($this->jwtAs($this->user))
            ->postJson('/api/grades', [
                'exam_id' => $this->exam->id,
                'class_id' => $this->class->id,
                'student_id' => $this->student->id,
                'total_correct' => 40,
                'score' => 8.0,
                'ocr_raw_name' => 'Nguyen Van A',
                'image_url' => 'https://res.cloudinary.com/test/image.jpg',
                'ai_confidence' => 0.95,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['grade' => ['id', 'studentName', 'totalCorrect', 'score']]);

        $this->assertDatabaseHas('grades', [
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'total_correct' => 40,
            'status' => 'confirmed',
        ]);
    }

    public function test_store_grade_with_sub_scores(): void
    {
        $response = $this->withHeaders($this->jwtAs($this->user))
            ->postJson('/api/grades', [
                'exam_id' => $this->exam->id,
                'class_id' => $this->class->id,
                'student_id' => $this->student->id,
                'total_correct' => 43,
                'score' => 43,
                'ocr_raw_name' => 'Nguyen Van A',
                'image_url' => 'https://res.cloudinary.com/test/name.jpg',
                'image_url_2' => 'https://res.cloudinary.com/test/scores.jpg',
                'ai_confidence' => 0.9,
                'sub_scores' => [
                    'vocabulary' => 10,
                    'grammar' => 8,
                    'listening' => 10,
                    'reading' => 5,
                    'writing' => 3,
                    'speaking' => 7,
                ],
            ]);

        $response->assertStatus(201);

        $grade = Grade::where('exam_id', $this->exam->id)
            ->where('student_id', $this->student->id)
            ->first();

        $this->assertSame('https://res.cloudinary.com/test/scores.jpg', $grade->image_url_2);
        $this->assertSame([
            'vocabulary' => 10,
            'grammar' => 8,
            'listening' => 10,
            'reading' => 5,
            'writing' => 3,
            'speaking' => 7,
        ], $grade->sub_scores);
    }

    public function test_store_grade_creates_new_student(): void
    {
        $response = $this->withHeaders($this->jwtAs($this->user))
            ->postJson('/api/grades', [
                'exam_id' => $this->exam->id,
                'class_id' => $this->class->id,
                'total_correct' => 35,
                'score' => 7.0,
                'ocr_raw_name' => 'Tran Van B',
                'create_new_student' => true,
                'new_student_name' => 'Trần Văn B',
                'image_url' => 'https://res.cloudinary.com/test/image.jpg',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('students', [
            'class_id' => $this->class->id,
            'full_name' => 'Trần Văn B',
        ]);
    }

    public function test_store_grade_fails_without_student_info(): void
    {
        $response = $this->withHeaders($this->jwtAs($this->user))
            ->postJson('/api/grades', [
                'exam_id' => $this->exam->id,
                'class_id' => $this->class->id,
                'total_correct' => 40,
                'score' => 8.0,
                'ocr_raw_name' => 'Nguyen Van A',
            ]);

        $response->assertStatus(422);
    }

    public function test_index_grades(): void
    {
        Grade::factory()->count(3)->create([
            'exam_id' => $this->exam->id,
            'class_id' => $this->class->id,
        ]);

        $response = $this->withHeaders($this->jwtAs($this->user))
            ->getJson('/api/grades?exam_id='.$this->exam->id);

        $response->assertStatus(200)
            ->assertJsonCount(3, 'grades');
    }

    public function test_update_grade(): void
    {
        $grade = Grade::factory()->create([
            'exam_id' => $this->exam->id,
            'class_id' => $this->class->id,
            'total_correct' => 40,
            'score' => 8.0,
        ]);

        $response = $this->withHeaders($this->jwtAs($this->user))
            ->putJson('/api/grades/'.$grade->id, [
                'total_correct' => 45,
                'score' => 9.0,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('grades', [
            'id' => $grade->id,
            'total_correct' => 45,
            'score' => 9.0,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_grades(): void
    {
        $response = $this->getJson('/api/grades?exam_id=1');
        $response->assertStatus(401);
    }

    public function test_store_grade_blocks_duplicate_within_five_minutes(): void
    {
        Grade::factory()->create([
            'exam_id' => $this->exam->id,
            'class_id' => $this->class->id,
            'student_id' => $this->student->id,
            'created_at' => now()->subMinutes(2),
        ]);

        $response = $this->withHeaders($this->jwtAs($this->user))
            ->postJson('/api/grades', [
                'exam_id' => $this->exam->id,
                'class_id' => $this->class->id,
                'student_id' => $this->student->id,
                'total_correct' => 40,
                'score' => 8.0,
                'ocr_raw_name' => 'Nguyen Van A',
            ]);

        $response->assertStatus(409)->assertJson(['error' => 'DUPLICATE']);
    }

    public function test_store_grade_allows_regrade_after_five_minutes(): void
    {
        Grade::factory()->create([
            'exam_id' => $this->exam->id,
            'class_id' => $this->class->id,
            'student_id' => $this->student->id,
            'created_at' => now()->subMinutes(10),
        ]);

        $response = $this->withHeaders($this->jwtAs($this->user))
            ->postJson('/api/grades', [
                'exam_id' => $this->exam->id,
                'class_id' => $this->class->id,
                'student_id' => $this->student->id,
                'total_correct' => 40,
                'score' => 8.0,
                'ocr_raw_name' => 'Nguyen Van A',
            ]);

        $response->assertStatus(201);
    }
}
