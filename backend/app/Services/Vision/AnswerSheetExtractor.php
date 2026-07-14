<?php

namespace App\Services\Vision;

interface AnswerSheetExtractor
{
    /**
     * @param  string  $imageBytes  Raw image bytes
     * @param  array   $pageSpec    Description of page: pageNumber, parts[] (partNumber, questionType, autoGradable, questions[])
     * @param  string|null  $mlkitHint  ML Kit text hint (optional)
     * @param  int  $attempt  1 = first call; >1 = retry after an incomplete/failed response
     */
    public function extractAnswers(string $imageBytes, array $pageSpec, ?string $mlkitHint, int $attempt = 1): PageAnswers;
}
