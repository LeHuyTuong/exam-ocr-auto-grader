<?php

namespace App\Models\Yle;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YleSubmissionPage extends Model
{
    protected $fillable = [
        'yle_submission_id',
        'page_number',
        'image_url',
        'ai_raw_response',
    ];

    protected function casts(): array
    {
        return [
            'ai_raw_response' => 'array',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(YleSubmission::class, 'yle_submission_id');
    }
}
