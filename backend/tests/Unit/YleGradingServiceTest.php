<?php

namespace Tests\Unit;

use App\Models\Yle\YleExam;
use App\Models\Yle\YlePart;
use App\Models\Yle\YleQuestion;
use App\Services\FuzzyMatchService;
use App\Services\YleGradingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YleGradingServiceTest extends TestCase
{
    use RefreshDatabase;

    private YleGradingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new YleGradingService(new FuzzyMatchService);
    }

    public function test_normalize_removes_hyphens(): void
    {
        $this->assertEquals('wall', $this->service->normalizeAnswer('W-A-L-L'));
        $this->assertEquals('tiger', $this->service->normalizeAnswer('T-I-G-E-R'));
    }

    public function test_normalize_converts_number_words(): void
    {
        $this->assertEquals('15', $this->service->normalizeAnswer('fifteen'));
        $this->assertEquals('3', $this->service->normalizeAnswer('three'));
        $this->assertEquals('10', $this->service->normalizeAnswer('ten'));
    }

    public function test_normalize_keeps_numbers(): void
    {
        $this->assertEquals('15', $this->service->normalizeAnswer('15'));
        $this->assertEquals('7', $this->service->normalizeAnswer('7'));
    }

    public function test_normalize_lowercases_and_trims(): void
    {
        $this->assertEquals('hello', $this->service->normalizeAnswer('  HELLO  '));
        $this->assertEquals('sam', $this->service->normalizeAnswer('SAM'));
    }

    public function test_normalize_removes_diacritics(): void
    {
        $this->assertEquals('nguyen', $this->service->normalizeAnswer('Nguyễn'));
    }

    public function test_grade_answer_exact_match(): void
    {
        $question = YleQuestion::make([
            'correct_answer' => 'Sam',
            'accepted_variants' => ['SAM', 'sam'],
            'points' => 1,
        ]);

        $result = $this->service->gradeAnswer('Sam', $question);
        $this->assertTrue($result['is_correct']);
        $this->assertEquals(1, $result['marks_awarded']);
    }

    public function test_grade_answer_variant_match(): void
    {
        $question = YleQuestion::make([
            'correct_answer' => '15',
            'accepted_variants' => ['fifteen'],
            'points' => 1,
        ]);

        $result = $this->service->gradeAnswer('fifteen', $question);
        $this->assertTrue($result['is_correct']);
        $this->assertEquals(1, $result['marks_awarded']);
    }

    public function test_grade_answer_hyphenated_spelling(): void
    {
        $question = YleQuestion::make([
            'correct_answer' => 'WALL',
            'accepted_variants' => [],
            'points' => 1,
        ]);

        $result = $this->service->gradeAnswer('W-A-L-L', $question);
        $this->assertTrue($result['is_correct']);
    }

    public function test_grade_answer_incorrect(): void
    {
        $question = YleQuestion::make([
            'correct_answer' => 'Sam',
            'accepted_variants' => [],
            'points' => 1,
        ]);

        $result = $this->service->gradeAnswer('Tom', $question);
        $this->assertFalse($result['is_correct']);
        $this->assertEquals(0, $result['marks_awarded']);
    }

    public function test_grade_answer_handles_empty_variants(): void
    {
        $question = YleQuestion::make([
            'correct_answer' => null,
            'accepted_variants' => [],
            'points' => 1,
        ]);

        $result = $this->service->gradeAnswer('anything', $question);
        $this->assertFalse($result['is_correct']);
        $this->assertEquals(0, $result['marks_awarded']);
    }

    public function test_normalize_tick_cross(): void
    {
        $this->assertEquals('tick', $this->service->normalizeTickCross('✓'));
        $this->assertEquals('tick', $this->service->normalizeTickCross('tick'));
        $this->assertEquals('cross', $this->service->normalizeTickCross('✗'));
        $this->assertEquals('cross', $this->service->normalizeTickCross('cross'));
        $this->assertEquals('tick', $this->service->normalizeTickCross('yes'));
        $this->assertEquals('cross', $this->service->normalizeTickCross('no'));
    }

    public function test_grade_tick_cross(): void
    {
        $question = YleQuestion::make([
            'correct_answer' => 'tick',
            'accepted_variants' => ['yes', '✓'],
            'points' => 1,
        ]);

        $result = $this->service->gradeTickCross('✓', $question);
        $this->assertTrue($result['is_correct']);

        $result = $this->service->gradeTickCross('yes', $question);
        $this->assertTrue($result['is_correct']);

        $result = $this->service->gradeTickCross('cross', $question);
        $this->assertFalse($result['is_correct']);
    }

    public function test_grade_yes_no(): void
    {
        $question = YleQuestion::make([
            'correct_answer' => 'yes',
            'accepted_variants' => ['y'],
            'points' => 1,
        ]);

        $result = $this->service->gradeTickCross('yes', $question);
        $this->assertTrue($result['is_correct']);

        $result = $this->service->gradeTickCross('no', $question);
        $this->assertFalse($result['is_correct']);
    }

    public function test_grading_with_real_exam_structure(): void
    {
        $user = \App\Models\User::factory()->create();
        $exam = \App\Models\Yle\YleExam::create([
            'level' => 'starters',
            'skill' => 'listening',
            'name' => 'Test Starters Listening',
            'total_marks' => 20,
            'total_pages' => 3,
            'created_by' => $user->id,
        ]);

        $part2 = \App\Models\Yle\YlePart::create([
            'yle_exam_id' => $exam->id,
            'part_number' => 2,
            'title' => 'Part 2',
            'question_type' => 'fill_blank',
            'is_auto_gradable' => true,
            'max_marks' => 5,
            'page_number' => 2,
            'sort_order' => 2,
        ]);

        $q1 = \App\Models\Yle\YleQuestion::create([
            'yle_part_id' => $part2->id,
            'question_number' => 1,
            'correct_answer' => 'Sam',
            'accepted_variants' => ['SAM'],
            'points' => 1,
        ]);

        $q2 = \App\Models\Yle\YleQuestion::create([
            'yle_part_id' => $part2->id,
            'question_number' => 2,
            'correct_answer' => '15',
            'accepted_variants' => ['fifteen', 'FIFTEEN'],
            'points' => 1,
        ]);

        $result1 = $this->service->gradeAnswer('Sam', $q1);
        $this->assertTrue($result1['is_correct']);

        $result2 = $this->service->gradeAnswer('fifteen', $q2);
        $this->assertTrue($result2['is_correct']);

        $result3 = $this->service->gradeAnswer('F-I-F-T-E-E-N', $q2);
        $this->assertTrue($result3['is_correct']);

        $result4 = $this->service->gradeAnswer('Tom', $q1);
        $this->assertFalse($result4['is_correct']);
    }
}
