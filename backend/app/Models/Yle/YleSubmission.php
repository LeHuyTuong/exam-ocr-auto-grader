<?php

namespace App\Models\Yle;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class YleSubmission extends Model
{
    use Auditable;

    protected $fillable = [
        'yle_exam_id',
        'class_id',
        'student_id',
        'exam_date',
        'ocr_raw_name',
        'auto_score',
        'manual_score',
        'total_score',
        'max_score',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'auto_score' => 'float',
            'manual_score' => 'float',
            'total_score' => 'float',
            'max_score' => 'float',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(YleExam::class, 'yle_exam_id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(YleSubmissionPage::class, 'yle_submission_id')->orderBy('page_number');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(YleAnswer::class, 'yle_submission_id');
    }

    protected function getAuditAttributes(): array
    {
        return [
            'student_id' => $this->student_id,
            'status' => $this->status,
            'auto_score' => $this->auto_score,
            'manual_score' => $this->manual_score,
            'total_score' => $this->total_score,
            'class_id' => $this->class_id,
        ];
    }
}
