<?php

namespace App\Services\Vision;

interface AnswerSheetExtractor
{
    /**
     * @param  string  $imageBytes  Raw image bytes
     * @param  array   $pageSpec    Description of page: pageNumber, parts[] (partNumber, questionType, questions[])
     * @param  string|null  $mlkitHint  ML Kit text hint (optional)
     */
    public function extractAnswers(string $imageBytes, array $pageSpec, ?string $mlkitHint): PageAnswers;
}
