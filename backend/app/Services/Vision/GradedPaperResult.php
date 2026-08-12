<?php

namespace App\Services\Vision;

class GradedPaperResult
{
    /**
     * Điểm để kiểu float (không phải int) vì giáo viên chấm tay thường ghi
     * điểm lẻ 0.5 trên bài — đọc thành int là mất luôn phần lẻ đó.
     *
     * @param  array{vocabulary:?float,grammar:?float,listening:?float,reading:?float,writing:?float,speaking:?float}|null  $subScores
     */
    public function __construct(
        public readonly ?string $studentName,
        public readonly ?float $totalScore,
        public readonly ?array $subScores,
        public readonly float $confidence,
    ) {}
}
