<?php

namespace App\Services\Vision;

/**
 * Builds the prompt for reading an already-hand-graded Unit Test page.
 *
 * The teacher grades on paper first: student name in pencil, then a score
 * strip in red ink (Vocabulary/Grammar/Listening/Reading/Writing/Speaking +
 * TOTAL, each written as "n/max"). The photo is a tight crop of ONE of
 * those two things — never the whole page — so the model only has to read
 * what's actually there instead of guessing which handwriting is relevant.
 */
class GradedPaperPrompt
{
    public static function build(string $mode, ?string $mlkitHint): string
    {
        $hint = $mlkitHint
            ? "\n\nA rough OCR text dump of the crop is below — use it only to help read unclear handwriting, never as the answer itself:\n\"{$mlkitHint}\""
            : '';

        if ($mode === 'name') {
            return <<<PROMPT
You are reading a tight photo crop of the "Name:" line on a graded Unit Test paper.

The student's name is handwritten IN PENCIL, next to or below a printed "Name:" label.
- Read ONLY the pencil handwriting. Ignore the printed "Name:" label itself.
- If the pencil writing is faint or unclear, still give your best reading and lower "confidence" accordingly.
- Set "confidence" (0-1) for how clearly you could read the handwritten name.

Return ONLY valid JSON, no extra text, in exactly this shape:
{"studentName":"...","confidence":0.9}{$hint}
PROMPT;
        }

        return <<<PROMPT
You are reading a tight photo crop of the score strip at the end of a graded Unit Test paper.

The teacher has already marked this paper and written scores IN RED INK, each under a
printed skill label, in the format "n/max" (e.g. "8/10"). The skills, in order, are:
Vocabulary, Grammar, Listening, Reading, Writing, Speaking — followed by a TOTAL box.

Rules:
- ONLY read the red handwritten numbers. Ignore the printed "/max" denominators — you only
  need the numerator (the number the teacher wrote), not the printed maximum.
- Read all six skill scores plus the TOTAL score.
- If a number is smudged or unclear, still give your best reading and lower "confidence".
- Set "confidence" (0-1) for how clearly you could read the red handwriting overall.

Return ONLY valid JSON, no extra text, in exactly this shape:
{"subScores":{"vocabulary":10,"grammar":8,"listening":10,"reading":5,"writing":3,"speaking":7},"totalScore":43,"confidence":0.9}{$hint}
PROMPT;
    }
}
