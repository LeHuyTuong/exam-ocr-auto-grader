<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'class_id',
        'name',
        'total_questions',
        'max_score',
        'grading_mode',
        'created_by',
    ];

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class, 'exam_id');
    }

    protected function getAuditAttributes(): array
    {
        return [
            'total_questions' => $this->total_questions,
            'max_score' => $this->max_score,
            'grading_mode' => $this->grading_mode,
            'class_id' => $this->class_id,
        ];
    }
}
