<?php

namespace App\Services\Vision;

use Illuminate\Support\Facades\Log;

class GradedPaperExtractorChain implements GradedPaperExtractor
{
    /** @param GradedPaperExtractor[] $extractors */
    public function __construct(private readonly array $extractors) {}

    public function extract(string $imageBytes, string $mode, ?string $mlkitHint): GradedPaperResult
    {
        $errors = [];

        foreach ($this->extractors as $extractor) {
            try {
                return $extractor->extract($imageBytes, $mode, $mlkitHint);
            } catch (\Throwable $e) {
                $errors[] = get_class($extractor).': '.$e->getMessage();
                Log::warning('Graded paper extractor failed, trying next provider', [
                    'extractor' => get_class($extractor),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw new \RuntimeException('All graded paper extractors failed: '.implode(' | ', $errors));
    }
}
