<?php

namespace App\Services\Vision;

/**
 * Builds the single, shared prompt used by every YLE answer-sheet extractor.
 *
 * Design: Gemini (and the other vision models) are treated as an *exam parser*,
 * not an OCR tool. We hand them the exact page structure (which parts, which
 * question numbers, which answer types) and tell them to report ONLY what the
 * student wrote — ignoring the printed question text, the worked example and
 * pictures. The model must return one entry per gradable question (empty string
 * when blank) so that a dropped question is a reliable signal, not silent loss.
 */
class AnswerSheetPrompt
{
    /**
     * @param  array  $pageSpec  pageNumber, parts[] (partNumber, questionType, autoGradable, questions[])
     * @param  int  $attempt  1 = first call; >1 = retry after an incomplete response
     */
    public static function build(array $pageSpec, ?string $mlkitHint, int $attempt = 1): string
    {
        $pageNumber = $pageSpec['pageNumber'];
        $parts = $pageSpec['parts'] ?? [];

        $gradable = [];
        $context = [];
        $gradableCount = 0;

        foreach ($parts as $part) {
            $qNums = array_map(fn ($q) => $q['questionNumber'], $part['questions'] ?? []);
            $line = "- Part {$part['partNumber']} ({$part['questionType']}): questions [".implode(', ', $qNums).']';

            // Default to gradable when the caller does not specify, to stay safe.
            if ($part['autoGradable'] ?? true) {
                $gradable[] = $line;
                $gradableCount += count($qNums);
            } else {
                $context[] = $line;
            }
        }

        $gradableDesc = $gradable
            ? implode("\n", $gradable)
            : '(none — return an empty "answers" array)';

        $contextDesc = $context
            ? "\n\nParts shown for LAYOUT CONTEXT ONLY — do NOT return answers for these:\n".implode("\n", $context)
            : '';

        $nameInstr = $pageNumber === 1
            ? "\n- \"studentName\": the full name the student wrote on the paper (usually at the top). If none is visible, use an empty string."
            : '';

        $retryEmphasis = $attempt > 1
            ? "\n\nRETRY: your previous response was missing some required questions. You MUST return exactly one entry for every question number in the gradable parts ({$gradableCount} total), using \"value\":\"\" for any the student left blank. Do not skip any."
            : '';

        $hint = $mlkitHint
            ? "\n\nA rough OCR text dump of the page is below — use it only to help read unclear handwriting, never as the answer itself:\n\"{$mlkitHint}\""
            : '';

        return <<<PROMPT
You are a Cambridge YLE (Young Learners English) exam parser — NOT an OCR tool.
Your only job is to report what THE STUDENT wrote or marked on this one exam page.

Page number: {$pageNumber}

Extract answers for these parts and questions:
{$gradableDesc}{$contextDesc}

CRITICAL — what to read and what to ignore:
- ONLY read the student's own marks: handwriting, letters/words they wrote, ticks (✓), crosses (✗), circles, and lines they drew.
- IGNORE everything printed on the page: the question text, the instructions, the worked EXAMPLE, the pictures, the word boxes, headers and page numbers. Never copy a printed word or the example as if it were the student's answer.
- Return exactly one entry for EVERY question in the gradable parts, in order. If the student left a question blank, still include it with "value":"" (empty string). Never drop a question.

How to record each answer, by part type:
- "mcq_abc": the single letter A, B or C the student ticked or circled.
- "fill_blank" / "word_from_box" / "one_word" / "word_order": the word(s) or number the student wrote, exactly as written.
- "tick_cross": "tick" if the student marked ✓, "cross" if ✗.
- "yes_no": "yes" or "no".{$nameInstr}

Confidence:
- Per answer, set "confidence" (0-1) = how clearly you can read that student mark. If unsure or the writing is messy, use a low value.
- Set overall "confidence" (0-1) for the whole page.

Return ONLY valid JSON, no extra text, in exactly this shape:
{"answers":[{"partNumber":1,"questionNumber":2,"value":"...","confidence":0.9}],"studentName":"...","confidence":0.9}{$retryEmphasis}{$hint}
PROMPT;
    }
}
