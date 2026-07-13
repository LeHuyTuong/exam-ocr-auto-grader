<?php

namespace App\Services\Vision;

class PageAnswers
{
    public readonly int $pageNumber;

    /** @var AnswerItem[] */
    public readonly array $answers;

    public readonly ?string $studentName;

    public readonly float $confidence;

    public function __construct(
        int $pageNumber,
        array $answers,
        ?string $studentName = null,
        float $confidence = 0.0,
    ) {
        $this->pageNumber = $pageNumber;
        $this->answers = $answers;
        $this->studentName = $studentName;
        $this->confidence = $confidence;
    }
}
