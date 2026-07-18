<?php

namespace App\Models\Yle;

use App\Models\User;
use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class YleExam extends Model
{
    use Auditable;

    protected $fillable = [
        'level',
        'skill',
        'name',
        'total_marks',
        'total_pages',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(YlePart::class, 'yle_exam_id')->orderBy('sort_order');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(YleSubmission::class, 'yle_exam_id');
    }

    protected function getAuditAttributes(): array
    {
        return [
            'name' => $this->name,
            'level' => $this->level,
            'skill' => $this->skill,
            'total_marks' => $this->total_marks,
            'total_pages' => $this->total_pages,
        ];
    }
}
