<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ExamTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwtAuth();
        $this->user = $this->jwtAsTeacher();
    }

    public function test_get_today_exam_returns_404_when_no_exam(): void
    {
        $class = SchoolClass::factory()->create();
        $this->user->classes()->attach($class->id);

        $response = $this->withHeaders($this->jwtAs($this->user))
            ->getJson('/api/exams/today?class_id='.$class->id);

        $response->assertStatus(404)
            ->assertJson(['error' => 'NOT_FOUND']);
    }

    public function test_get_today_exam_returns_existing_exam(): void
    {
        $exam = Exam::factory()->create();
        $this->user->classes()->attach($exam->class_id);

        $response = $this->withHeaders($this->jwtAs($this->user))
            ->getJson('/api/exams/today?class_id='.$exam->class_id);

        $response->assertStatus(200)
            ->assertJsonStructure(['exam' => ['id', 'name', 'totalQuestions', 'maxScore']]);
    }

    public function test_create_today_exam_successfully(): void
    {
        $class = SchoolClass::factory()->create();
        $this->user->classes()->attach($class->id);

        $response = $this->withHeaders($this->jwtAs($this->user))
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
        $exam = Exam::factory()->create();
        $this->user->classes()->attach($exam->class_id);

        $response = $this->withHeaders($this->jwtAs($this->user))
            ->postJson('/api/exams/today', [
                'class_id' => $exam->class_id,
                'total_questions' => 99,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('exams', ['id' => $exam->id, 'total_questions' => $exam->total_questions]);
        $this->assertDatabaseMissing('exams', ['total_questions' => 99]);
    }

    public function test_create_today_exam_with_graded_mode(): void
    {
        $class = SchoolClass::factory()->create();
        $this->user->classes()->attach($class->id);

        $response = $this->withHeaders($this->jwtAs($this->user))
            ->postJson('/api/exams/today', [
                'class_id' => $class->id,
                'total_questions' => 50,
                'max_score' => 50,
                'grading_mode' => 'graded',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('exam.gradingMode', 'graded');

        $this->assertDatabaseHas('exams', [
            'class_id' => $class->id,
            'grading_mode' => 'graded',
        ]);

        // Re-fetching later must surface the same mode so the app knows
        // which scan screen to route to without asking again.
        $today = $this->withHeaders($this->jwtAs($this->user))
            ->getJson('/api/exams/today?class_id='.$class->id);

        $today->assertStatus(200)->assertJsonPath('exam.gradingMode', 'graded');
    }

    public function test_create_exam_defaults_to_counting_mode(): void
    {
        $class = SchoolClass::factory()->create();
        $this->user->classes()->attach($class->id);

        $response = $this->withHeaders($this->jwtAs($this->user))
            ->postJson('/api/exams/today', [
                'class_id' => $class->id,
                'total_questions' => 50,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('exam.gradingMode', 'counting');
    }

    public function test_create_exam_validates_required_fields(): void
    {
        $response = $this->withHeaders($this->jwtAs($this->user))
            ->postJson('/api/exams/today', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['class_id', 'total_questions']);
    }

    public function test_unauthenticated_user_cannot_access_exams(): void
    {
        $response = $this->getJson('/api/exams/today');
        $response->assertStatus(401);
    }

    public function test_export_grades_to_excel(): void
    {
        $exam = Exam::factory()->create(['max_score' => 50]);
        $this->user->classes()->attach($exam->class_id);

        $studentA = Student::factory()->create(['class_id' => $exam->class_id, 'full_name' => 'Nguyen Van A']);
        $studentB = Student::factory()->create(['class_id' => $exam->class_id, 'full_name' => 'Tran Van B']);

        Grade::factory()->create([
            'exam_id' => $exam->id,
            'class_id' => $exam->class_id,
            'student_id' => $studentA->id,
            'score' => 43,
            'sub_scores' => [
                'vocabulary' => 10, 'grammar' => 8, 'listening' => 10,
                'reading' => 5, 'writing' => 3, 'speaking' => 7,
            ],
        ]);
        Grade::factory()->create([
            'exam_id' => $exam->id,
            'class_id' => $exam->class_id,
            'student_id' => $studentB->id,
            'score' => 30,
            'sub_scores' => null,
        ]);

        $response = $this->withHeaders($this->jwtAs($this->user))
            ->get('/api/exams/'.$exam->id.'/export');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmp, $response->streamedContent());

        $sheet = IOFactory::load($tmp)->getActiveSheet();

        $this->assertSame('NO', $sheet->getCell('A1')->getValue());
        $this->assertSame('NAME', $sheet->getCell('B1')->getValue());

        // Sorted by name: Nguyen Van A before Tran Van B.
        $this->assertSame('Nguyen Van A', $sheet->getCell('B2')->getValue());
        $this->assertEquals(10, $sheet->getCell('C2')->getValue());
        $this->assertEquals(43, $sheet->getCell('I2')->getValue());

        $this->assertSame('Tran Van B', $sheet->getCell('B3')->getValue());
        $this->assertNull($sheet->getCell('C3')->getValue());
        $this->assertEquals(30, $sheet->getCell('I3')->getValue());

        // Remarks/personality columns always stay blank for the teacher to fill in.
        $this->assertNull($sheet->getCell('J2')->getValue());

        unlink($tmp);
    }

    public function test_export_forbidden_for_other_class(): void
    {
        $exam = Exam::factory()->create();

        $response = $this->withHeaders($this->jwtAs($this->user))
            ->get('/api/exams/'.$exam->id.'/export');

        $response->assertStatus(403);
    }
}
