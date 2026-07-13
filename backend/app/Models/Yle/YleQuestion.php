<?php

namespace App\Models\Yle;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YleQuestion extends Model
{
    protected $fillable = [
        'yle_part_id',
        'question_number',
        'prompt',
        'correct_answer',
        'accepted_variants',
        'points',
    ];

    protected function casts(): array
    {
        return [
            'accepted_variants' => 'array',
        ];
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(YlePart::class, 'yle_part_id');
    }
}
