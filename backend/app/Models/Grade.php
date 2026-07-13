<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grade extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'student_id',
        'class_id',
        'total_correct',
        'score',
        'image_url',
        'ai_confidence',
        'ocr_raw_name',
        'status',
        'confirmed_by',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'float',
            'ai_confidence' => 'float',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
