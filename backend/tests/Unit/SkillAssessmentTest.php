<?php

namespace Tests\Unit;

use App\Support\SkillAssessment;
use Tests\TestCase;

class SkillAssessmentTest extends TestCase
{
    public function test_weak_skills_text_matches_excel_textjoin_formula(): void
    {
        // Tương đương IF(C<9;vocab) IF(D<9;grammar) IF(E<9;nghe)
        // IF(F<4;doc) IF(G<4;viet) IF(H<9;noi)
        $subScores = [
            'vocabulary' => 9,    // đạt
            'grammar' => 8,       // yếu (<9)
            'listening' => 10,    // đạt
            'reading' => 3,       // yếu (<4)
            'writing' => 4,       // đạt
            'speaking' => 7,      // yếu (<9)
        ];

        $this->assertSame('Ngữ pháp, Kỹ năng đọc, Kỹ năng nói', SkillAssessment::weakSkillsText($subScores));
    }

    public function test_weak_skills_text_empty_when_all_pass(): void
    {
        $subScores = [
            'vocabulary' => 9, 'grammar' => 10, 'listening' => 9,
            'reading' => 5, 'writing' => 4, 'speaking' => 9,
        ];

        $this->assertSame('', SkillAssessment::weakSkillsText($subScores));
    }

    public function test_weak_skills_text_empty_when_no_sub_scores(): void
    {
        // Bài "đếm câu đúng" không có sub_scores -> để trống.
        $this->assertSame('', SkillAssessment::weakSkillsText(null));
        $this->assertSame('', SkillAssessment::weakSkillsText([]));
    }

    public function test_missing_skill_key_is_ignored_not_counted_weak(): void
    {
        // Chỉ chấm 2 kỹ năng, 1 yếu -> chỉ label kỹ năng đó.
        $subScores = ['vocabulary' => 10, 'speaking' => 5];

        $this->assertSame('Kỹ năng nói', SkillAssessment::weakSkillsText($subScores));
    }

    public function test_thresholds_loaded_from_config(): void
    {
        $thresholds = SkillAssessment::thresholds();

        $this->assertSame(9, $thresholds['vocabulary']['pass']);
        $this->assertSame(10, $thresholds['vocabulary']['max']);
        $this->assertSame(4, $thresholds['reading']['pass']);
        $this->assertSame(5, $thresholds['reading']['max']);
        $this->assertSame(50, SkillAssessment::totalMax());
    }

    public function test_total_from_sub_scores(): void
    {
        $subScores = [
            'vocabulary' => 10, 'grammar' => 8, 'listening' => 10,
            'reading' => 5, 'writing' => 3, 'speaking' => 7,
        ];

        $this->assertSame(43.0, SkillAssessment::totalFromSubScores($subScores));
        $this->assertNull(SkillAssessment::totalFromSubScores(null));
        $this->assertNull(SkillAssessment::totalFromSubScores([]));
    }

    public function test_average_skill_handles_nulls(): void
    {
        $list = [
            ['vocabulary' => 9, 'grammar' => 7],
            ['vocabulary' => null, 'grammar' => 9],
            ['vocabulary' => 10],  // grammar thiếu
        ];

        $this->assertSame(9.5, SkillAssessment::averageSkill('vocabulary', $list));
        $this->assertSame(8.0, SkillAssessment::averageSkill('grammar', $list));
        $this->assertNull(SkillAssessment::averageSkill('listening', $list));
    }

    public function test_is_weak_boundary(): void
    {
        // Bằng đúng ngưỡng = đạt (không yếu).
        $this->assertFalse(SkillAssessment::isWeak('vocabulary', 9));
        $this->assertFalse(SkillAssessment::isWeak('reading', 4));
        // Dưới 1 = yếu.
        $this->assertTrue(SkillAssessment::isWeak('vocabulary', 8.9));
        $this->assertTrue(SkillAssessment::isWeak('reading', 3));
        // Null/thiếu = không yếu.
        $this->assertFalse(SkillAssessment::isWeak('vocabulary', null));
    }
}
