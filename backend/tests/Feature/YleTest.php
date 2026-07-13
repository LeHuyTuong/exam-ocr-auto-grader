<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Models\Yle\YleExam;
use App\Models\Yle\YlePart;
use App\Models\Yle\YleSubmission;
use App\Models\Yle\YleSubmissionPage;
use App\Models\Yle\YleQuestion;
use App\Models\Yle\YleAnswer;
use App\Support\YleTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private SchoolClass $class;
    private Student $student;
    private YleExam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpJwtAuth();
        $this->user = $this->jwtAsAdmin();
        $this->class = SchoolClass::factory()->create();
        $this->user->classes()->attach($this->class->id);
        $this->student = Student::factory()->create([
            'class_id' => $this->class->id,
            'full_name' => 'Nguyen Van A',
        ]);

        $template = YleTemplates::get('starters', 'listening');
        $this->exam = YleExam::create([
            'level' => 'starters',
            'skill' => 'listening',
            'name' => $template['name'],
            'total_marks' => $template['total_marks'],
            'total_pages' => $template['total_pages'],
            'created_by' => $this->user->id,
        ]);

        foreach ($template['parts'] as $partData) {
            $questions = $partData['questions'];
            unset($partData['questions']);
            $part = YlePart::create(array_merge($partData, ['yle_exam_id' => $this->exam->id]));
            foreach ($questions as $qData) {
                YleQuestion::create(array_merge($qData, ['yle_part_id' => $part->id]));
            }
        }

        $part2 = YlePart::where('yle_exam_id', $this->exam->id)->where('part_number', 2)->first();
        foreach ($part2->questions as $q) {
            if ($q->question_number <= 3) {
                $q->update(['correct_answer' => 'Sam', 'accepted_variants' => ['SAM']]);
            } else {
                $q->update(['correct_answer' => '15', 'accepted_variants' => ['fifteen']]);
            }
        }

        $part3 = YlePart::where('yle_exam_id', $this->exam->id)->where('part_number', 3)->first();
        foreach ($part3->questions as $q) {
            $q->update(['correct_answer' => 'B', 'accepted_variants' => ['b']]);
        }
    }

    public function test_list_exams(): void
    {
        $response = $this->withHeaders($this->jwtAs($this->user))
            ->getJson('/api/yle/exams');

        $response->assertStatus(200)
            ->assertJsonStructure(['exams']);
    }

    public function test_create_exam_from_template(): void
    {
        $response = $this->withHeaders($this->jwtAs($this->user))
            ->postJson('/api/yle/exams', [
                'level' => 'starters',
                'skill' => 'listening',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['exam' => ['id', 'parts']]);

        $this->assertDatabaseHas('yle_exams', [
            'id' => $response->json('exam.id'),
            'level' => 'starters',
            'skill' => 'listening',
        ]);
    }

    public function test_create_exam_invalid_level(): void
    {
        $response = $this->withHeaders($this->jwtAs($this->user))
            ->postJson('/api/yle/exams', [
                'level' => 'invalid',
                'skill' => 'listening',
            ]);

        $response->assertStatus(422);
    }

    public function test_update_question_answer_key(): void
    {
        $question = YleQuestion::whereHas('part', fn ($q) => $q->where('yle_exam_id', $this->exam->id))
            ->first();

        $response = $this->withHeaders($this->jwtAs($this->user))
            ->putJson("/api/yle/questions/{$question->id}", [
                'correct_answer' => 'A',
                'accepted_variants' => ['a', 'A'],
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('yle_questions', [
            'id' => $question->id,
            'correct_answer' => 'A',
        ]);
    }

    public function test_create_submission(): void
    {
        $response = $this->withHeaders($this->jwtAs($this->user))
            ->postJson('/api/yle/submissions', [
                'yle_exam_id' => $this->exam->id,
                'class_id' => $this->class->id,
                'exam_date' => '2026-07-13',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('submission.status', 'pending')
            ->assertJsonPath('submission.maxScore', 20);
    }

    public function test_create_submission_forbidden_class(): void
    {
        $otherClass = SchoolClass::factory()->create();
        $otherUser = $this->jwtAsTeacher();

        $response = $this->withHeaders($this->jwtAs($otherUser))
            ->postJson('/api/yle/submissions', [
                'yle_exam_id' => $this->exam->id,
                'class_id' => $this->class->id,
                'exam_date' => '2026-07-13',
            ]);

        $response->assertStatus(403);
    }

    public function test_assign_student_to_submission(): void
    {
        $submission = YleSubmission::create([
            'yle_exam_id' => $this->exam->id,
            'class_id' => $this->class->id,
            'exam_date' => '2026-07-13',
            'max_score' => 20,
            'status' => 'pending',
            'created_by' => $this->user->id,
        ]);

        $response = $this->withHeaders($this->jwtAs($this->user))
            ->putJson("/api/yle/submissions/{$submission->id}/student", [
                'student_id' => $this->student->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('submission.studentId', $this->student->id)
            ->assertJsonPath('submission.studentName', 'Nguyen Van A');
    }

    public function test_duplicate_student_rejected(): void
    {
        $sub1 = YleSubmission::create([
            'yle_exam_id' => $this->exam->id,
            'class_id' => $this->class->id,
            'student_id' => $this->student->id,
            'exam_date' => '2026-07-13',
            'max_score' => 20,
            'status' => 'pending',
            'created_by' => $this->user->id,
        ]);

        $sub2 = YleSubmission::create([
            'yle_exam_id' => $this->exam->id,
            'class_id' => $this->class->id,
            'exam_date' => '2026-07-13',
            'max_score' => 20,
            'status' => 'pending',
            'created_by' => $this->user->id,
        ]);

        $response = $this->withHeaders($this->jwtAs($this->user))
            ->putJson("/api/yle/submissions/{$sub2->id}/student", [
                'student_id' => $this->student->id,
            ]);

        $response->assertStatus(409)
            ->assertJson(['error' => 'DUPLICATE']);
    }

    public function test_add_manual_marks(): void
    {
        $submission = YleSubmission::create([
            'yle_exam_id' => $this->exam->id,
            'class_id' => $this->class->id,
            'exam_date' => '2026-07-13',
            'max_score' => 20,
            'status' => 'pending',
            'created_by' => $this->user->id,
        ]);

        $part1 = YlePart::where('yle_exam_id', $this->exam->id)->where('part_number', 1)->first();
        $part4 = YlePart::where('yle_exam_id', $this->exam->id)->where('part_number', 4)->first();

        $response = $this->withHeaders($this->jwtAs($this->user))
            ->postJson("/api/yle/submissions/{$submission->id}/manual", [
                'marks' => [
                    ['part_id' => $part1->id, 'marks' => 4],
                    ['part_id' => $part4->id, 'marks' => 3],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('submission.manualScore', 7)
            ->assertJsonPath('submission.totalScore', 7);
    }

    public function test_view_submission_breakdown(): void
    {
        $submission = YleSubmission::create([
            'yle_exam_id' => $this->exam->id,
            'class_id' => $this->class->id,
            'student_id' => $this->student->id,
            'exam_date' => '2026-07-13',
            'max_score' => 20,
            'status' => 'pending',
            'created_by' => $this->user->id,
        ]);

        $part1 = YlePart::where('yle_exam_id', $this->exam->id)->where('part_number', 1)->first();
        foreach ($part1->questions as $i => $q) {
            YleAnswer::create([
                'yle_submission_id' => $submission->id,
                'yle_question_id' => $q->id,
                'is_correct' => $i < 4,
                'marks_awarded' => $i < 4 ? 1 : 0,
                'graded_by' => 'manual',
            ]);
        }

        $response = $this->withHeaders($this->jwtAs($this->user))
            ->getJson("/api/yle/submissions/{$submission->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['submission', 'breakdown']);

        $part1Breakdown = collect($response->json('breakdown'))->firstWhere('partNumber', 1);
        $this->assertEquals(4, $part1Breakdown['score']);
        $this->assertEquals(5, $part1Breakdown['maxMarks']);
    }

    public function test_unauthenticated_cannot_access(): void
    {
        $response = $this->getJson('/api/yle/exams');
        $response->assertStatus(401);
    }
}
