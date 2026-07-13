<?php

namespace App\Services;

use App\Models\Yle\YleQuestion;

class YleGradingService
{
    private FuzzyMatchService $fuzzyMatch;

    public function __construct(FuzzyMatchService $fuzzyMatch)
    {
        $this->fuzzyMatch = $fuzzyMatch;
    }

    /**
     * Grade a single answer against a question's correct answer and accepted variants.
     *
     * @return array{is_correct: bool, marks_awarded: int}
     */
    public function gradeAnswer(string $studentAnswer, YleQuestion $question): array
    {
        $normalized = $this->normalizeAnswer($studentAnswer);

        $acceptedVariants = $question->accepted_variants ?? [];
        if ($question->correct_answer) {
            $acceptedVariants[] = $question->correct_answer;
        }

        if (empty($acceptedVariants)) {
            return ['is_correct' => false, 'marks_awarded' => 0];
        }

        foreach ($acceptedVariants as $variant) {
            $normalizedVariant = $this->normalizeAnswer((string) $variant);
            if ($normalized === $normalizedVariant) {
                return [
                    'is_correct' => true,
                    'marks_awarded' => $question->points,
                ];
            }
        }

        return ['is_correct' => false, 'marks_awarded' => 0];
    }

    /**
     * Normalize an answer for comparison.
     *
     * Steps:
     * 1. Lowercase
     * 2. Remove Vietnamese diacritics (via FuzzyMatchService)
     * 3. Strip hyphens between letters (e.g. W-A-L-L → wall)
     * 4. Optionally expand numbers to words (15 → fifteen) and vice versa
     * 5. Trim extra whitespace
     */
    public function normalizeAnswer(string $answer): string
    {
        $value = mb_strtolower(trim($answer));

        // Strip hyphens between letters: W-A-L-L → wall
        $value = preg_replace('/\b([a-zA-Z])-(?=[a-zA-Z])/u', '$1', $value);
        $value = str_replace('-', '', $value);

        // Remove Vietnamese diacritics
        $value = $this->fuzzyMatch->normalize($value);

        // Normalize numbers: "fifteen" → "15", "15" stays
        $wordsToNumbers = [
            'zero' => '0', 'one' => '1', 'two' => '2', 'three' => '3', 'four' => '4',
            'five' => '5', 'six' => '6', 'seven' => '7', 'eight' => '8', 'nine' => '9',
            'ten' => '10', 'eleven' => '11', 'twelve' => '12', 'thirteen' => '13',
            'fourteen' => '14', 'fifteen' => '15', 'sixteen' => '16', 'seventeen' => '17',
            'eighteen' => '18', 'nineteen' => '19', 'twenty' => '20',
        ];

        // Check if answer is a number word and convert
        if (isset($wordsToNumbers[$value])) {
            return $wordsToNumbers[$value];
        }

        return trim($value);
    }

    /**
     * Normalize tick/cross/yes/no answers.
     */
    public function normalizeTickCross(string $answer): string
    {
        $tickVariants = ['tick', 'check', 'yes', 'true', 'v', 'y'];
        $crossVariants = ['cross', 'no', 'false', 'x', 'n'];

        $trimmed = trim($answer);

        // Check raw symbols first (before normalization strips them)
        if (in_array($trimmed, ['✓', '✔'], true)) {
            return 'tick';
        }
        if (in_array($trimmed, ['✗', '✘'], true)) {
            return 'cross';
        }

        $normalized = $this->normalizeAnswer($answer);
        $cleaned = preg_replace('/[^a-zA-Z]/u', '', $normalized);

        foreach ($tickVariants as $v) {
            if ($cleaned === $v) {
                return 'tick';
            }
        }

        foreach ($crossVariants as $v) {
            if ($cleaned === $v) {
                return 'cross';
            }
        }

        return $normalized;
    }

    /**
     * Grade tick_cross or yes_no question type with special normalization.
     */
    public function gradeTickCross(string $studentAnswer, YleQuestion $question): array
    {
        $normalized = $this->normalizeTickCross($studentAnswer);

        $acceptedVariants = $question->accepted_variants ?? [];
        if ($question->correct_answer) {
            $acceptedVariants[] = $question->correct_answer;
        }

        foreach ($acceptedVariants as $variant) {
            $normalizedVariant = $this->normalizeTickCross((string) $variant);
            if ($normalized === $normalizedVariant) {
                return [
                    'is_correct' => true,
                    'marks_awarded' => $question->points,
                ];
            }
        }

        return ['is_correct' => false, 'marks_awarded' => 0];
    }
}
