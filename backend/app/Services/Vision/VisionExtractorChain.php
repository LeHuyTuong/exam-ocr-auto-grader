<?php

namespace App\Services\Vision;

use Illuminate\Support\Facades\Log;

class VisionExtractorChain implements VisionExtractor
{
    /** @param VisionExtractor[] $extractors tried in order until one succeeds */
    public function __construct(private readonly array $extractors) {}

    public function extract(string $imageBytes, ?string $mlkitHint): ExtractResult
    {
        $errors = [];

        foreach ($this->extractors as $extractor) {
            try {
                return $extractor->extract($imageBytes, $mlkitHint);
            } catch (\Throwable $e) {
                $errors[] = get_class($extractor).': '.$e->getMessage();
                Log::warning('Vision extractor failed, trying next provider', [
                    'extractor' => get_class($extractor),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw new \RuntimeException('All vision extractors failed: '.implode(' | ', $errors));
    }
}
