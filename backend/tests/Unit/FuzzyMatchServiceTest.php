<?php

namespace Tests\Unit;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\FuzzyMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuzzyMatchServiceTest extends TestCase
{
    use RefreshDatabase;

    private FuzzyMatchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FuzzyMatchService;
    }

    public function test_normalize_removes_diacritics(): void
    {
        $this->assertEquals('nguyen van a', $this->service->normalize('Nguyễn Văn A'));
        $this->assertEquals('tran thi b', $this->service->normalize('Trần Thị B'));
        $this->assertEquals('le hoang c', $this->service->normalize('Lê Hoàng C'));
    }

    public function test_normalize_handles_lowercase_and_trimming(): void
    {
        $this->assertEquals('pham van d', $this->service->normalize('  PHẠM VĂN D  '));
    }

    public function test_similarity_returns_1_for_exact_match(): void
    {
        $similarity = $this->service->similarity('nguyen van a', 'nguyen van a');
        $this->assertEquals(1.0, $similarity);
    }

    public function test_similarity_returns_high_for_similar_names(): void
    {
        $similarity = $this->service->similarity('nguyen van a', 'nguyen van b');
        $this->assertGreaterThan(0.7, $similarity);
    }

    public function test_similarity_returns_low_for_different_names(): void
    {
        $similarity = $this->service->similarity('nguyen van a', 'tran thi b');
        $this->assertLessThan(0.3, $similarity);
    }

    public function test_find_candidates_returns_top_matches(): void
    {
        $class = SchoolClass::factory()->create();

        $student1 = Student::factory()->create([
            'class_id' => $class->id,
            'full_name' => 'Nguyen Van A',
            'normalized_name' => $this->service->normalize('Nguyen Van A'),
        ]);

        $student2 = Student::factory()->create([
            'class_id' => $class->id,
            'full_name' => 'Tran Van B',
            'normalized_name' => $this->service->normalize('Tran Van B'),
        ]);

        $candidates = $this->service->findCandidates('Nguyen Van A', $class->id);

        $this->assertCount(2, $candidates);
        $this->assertEquals($student1->id, $candidates[0]['studentId']);
        $this->assertEquals(1.0, $candidates[0]['similarity']);
    }

    public function test_find_candidates_returns_empty_for_no_match(): void
    {
        $class = SchoolClass::factory()->create();

        Student::factory()->create([
            'class_id' => $class->id,
            'full_name' => 'Nguyen Van A',
            'normalized_name' => $this->service->normalize('Nguyen Van A'),
        ]);

        $candidates = $this->service->findCandidates('Tran Thi C', $class->id);

        $this->assertCount(1, $candidates);
    }

    public function test_find_candidates_uses_aliases(): void
    {
        $class = SchoolClass::factory()->create();

        $student = Student::factory()->create([
            'class_id' => $class->id,
            'full_name' => 'Nguyen Van A',
            'normalized_name' => $this->service->normalize('Nguyen Van A'),
            'aliases' => ['Nguyễn Văn A', 'A Nguyen Van'],
        ]);

        $candidates = $this->service->findCandidates('Nguyễn Văn A', $class->id);

        $this->assertNotEmpty($candidates);
    }

    public function test_normalize_and_similarity_tolerate_null(): void
    {
        // AI / student data can carry nulls; these must not throw a TypeError
        // (which is a \Error, not \Exception — it would escape the OCR
        // controller's catch and surface as a raw 500).
        $this->assertSame('', $this->service->normalize(null));
        $this->assertIsFloat($this->service->similarity('nguyen van a', null));
        $this->assertIsFloat($this->service->similarity(null, null));
    }

    public function test_find_candidates_survives_student_with_null_alias(): void
    {
        $class = SchoolClass::factory()->create();

        Student::factory()->create([
            'class_id' => $class->id,
            'full_name' => 'Nguyen Van A',
            'normalized_name' => $this->service->normalize('Nguyen Van A'),
            'aliases' => [null, 'nva'],
        ]);

        // Previously threw TypeError on normalize(null) -> uncaught 500.
        $candidates = $this->service->findCandidates('Nguyen Van A', $class->id);

        $this->assertNotEmpty($candidates);
        $this->assertEquals(1.0, $candidates[0]['similarity']);
    }

    public function test_find_candidates_returns_max_5_results(): void
    {
        $class = SchoolClass::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            Student::factory()->create([
                'class_id' => $class->id,
                'full_name' => 'Student '.chr(65 + $i),
                'normalized_name' => 'student '.chr(65 + $i),
            ]);
        }

        $candidates = $this->service->findCandidates('Student', $class->id);

        $this->assertLessThanOrEqual(5, count($candidates));
    }
}
