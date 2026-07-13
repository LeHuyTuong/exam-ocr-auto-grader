<?php

namespace App\Services\Vision;

class ExtractResult
{
    public function __construct(
        public readonly string $studentName,
        public readonly int $totalCorrect,
        public readonly float $confidence,
    ) {}
}
