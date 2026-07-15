<?php

namespace App\Services\Vision;

interface GradedPaperExtractor
{
    /**
     * @param  string  $imageBytes  Raw image bytes
     * @param  string  $mode  "name" (crop of the student-name line) or "scores" (crop of the score strip)
     * @param  string|null  $mlkitHint  ML Kit text hint (optional)
     */
    public function extract(string $imageBytes, string $mode, ?string $mlkitHint): GradedPaperResult;
}
