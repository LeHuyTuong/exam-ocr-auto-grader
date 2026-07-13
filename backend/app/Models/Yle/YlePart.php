<?php

namespace App\Models\Yle;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class YlePart extends Model
{
    protected $fillable = [
        'yle_exam_id',
        'part_number',
        'title',
        'question_type',
        'is_auto_gradable',
        'max_marks',
        'page_number',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_auto_gradable' => 'boolean',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(YleExam::class, 'yle_exam_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(YleQuestion::class, 'yle_part_id')->orderBy('question_number');
    }
}
