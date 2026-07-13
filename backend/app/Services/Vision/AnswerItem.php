<?php

namespace App\Services\Vision;

class AnswerItem
{
    public readonly int $partNumber;

    public readonly int $questionNumber;

    public readonly string $value;

    public readonly float $confidence;

    public function __construct(
        int $partNumber,
        int $questionNumber,
        string $value,
        float $confidence = 0.0,
    ) {
        $this->partNumber = $partNumber;
        $this->questionNumber = $questionNumber;
        $this->value = $value;
        $this->confidence = $confidence;
    }
}
