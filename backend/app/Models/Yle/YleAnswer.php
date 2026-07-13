<?php

namespace App\Models\Yle;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YleAnswer extends Model
{
    protected $fillable = [
        'yle_submission_id',
        'yle_question_id',
        'student_answer',
        'is_correct',
        'marks_awarded',
        'graded_by',
        'ai_confidence',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'ai_confidence' => 'float',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(YleSubmission::class, 'yle_submission_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(YleQuestion::class, 'yle_question_id');
    }
}
