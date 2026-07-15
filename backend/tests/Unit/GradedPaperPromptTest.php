<?php

namespace Tests\Unit;

use App\Services\Vision\GradedPaperPrompt;
use PHPUnit\Framework\TestCase;

class GradedPaperPromptTest extends TestCase
{
    public function test_name_mode_mentions_pencil_and_ignores_printed_label(): void
    {
        $prompt = GradedPaperPrompt::build('name', null);

        $this->assertStringContainsString('PENCIL', $prompt);
        $this->assertStringContainsString('studentName', $prompt);
    }

    public function test_scores_mode_mentions_red_ink_and_all_six_skills(): void
    {
        $prompt = GradedPaperPrompt::build('scores', null);

        $this->assertStringContainsString('RED INK', $prompt);
        foreach (['Vocabulary', 'Grammar', 'Listening', 'Reading', 'Writing', 'Speaking'] as $skill) {
            $this->assertStringContainsString($skill, $prompt);
        }
        $this->assertStringContainsString('TOTAL', $prompt);
    }

    public function test_includes_mlkit_hint_when_provided(): void
    {
        $prompt = GradedPaperPrompt::build('name', 'Nguyen Phuoc Hau');

        $this->assertStringContainsString('Nguyen Phuoc Hau', $prompt);
    }
}
