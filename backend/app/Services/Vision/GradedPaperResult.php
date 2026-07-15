<?php

namespace App\Services\Vision;

class GradedPaperResult
{
    /**
     * @param  array{vocabulary:?int,grammar:?int,listening:?int,reading:?int,writing:?int,speaking:?int}|null  $subScores
     */
    public function __construct(
        public readonly ?string $studentName,
        public readonly ?int $totalScore,
        public readonly ?array $subScores,
        public readonly float $confidence,
    ) {}
}
