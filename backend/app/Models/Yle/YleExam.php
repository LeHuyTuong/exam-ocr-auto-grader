<?php

namespace App\Models\Yle;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class YleExam extends Model
{
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
}
