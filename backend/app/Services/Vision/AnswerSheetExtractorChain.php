<?php

namespace App\Services\Vision;

use Illuminate\Support\Facades\Log;

class AnswerSheetExtractorChain implements AnswerSheetExtractor
{
    /** @param AnswerSheetExtractor[] $extractors */
    public function __construct(private readonly array $extractors) {}

    public function extractAnswers(string $imageBytes, array $pageSpec, ?string $mlkitHint): PageAnswers
    {
        $errors = [];

        foreach ($this->extractors as $extractor) {
            try {
                return $extractor->extractAnswers($imageBytes, $pageSpec, $mlkitHint);
            } catch (\Throwable $e) {
                $errors[] = get_class($extractor).': '.$e->getMessage();
                Log::warning('Answer sheet extractor failed, trying next provider', [
                    'extractor' => get_class($extractor),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw new \RuntimeException('All answer sheet extractors failed: '.implode(' | ', $errors));
    }
}
