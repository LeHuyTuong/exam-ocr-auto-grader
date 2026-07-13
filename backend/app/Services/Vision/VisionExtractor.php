<?php

namespace App\Services\Vision;

interface VisionExtractor
{
    public function extract(string $imageBytes, ?string $mlkitHint): ExtractResult;
}
