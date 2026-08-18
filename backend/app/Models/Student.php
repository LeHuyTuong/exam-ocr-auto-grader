<?php

namespace App\Models;

use App\Services\FuzzyMatchService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'full_name',
        'normalized_name',
        'aliases',
    ];

    protected function casts(): array
    {
        return [
            'aliases' => 'array',
        ];
    }

    /**
     * normalized_name là khoá phái sinh (tên viết thường, bỏ dấu) dùng để dò tên
     * OCR và để sắp ABC — luôn tự tính lại từ full_name. Trước đây form admin cho
     * gõ tay và fallback về nguyên tên có dấu, nên học sinh thêm từ trang quản trị
     * có khoá sai: dò tên kém chính xác, mà sắp ABC thì "Đỗ"/"Ân" rơi xuống cuối.
     */
    protected static function booted(): void
    {
        static::saving(function (self $student) {
            $student->normalized_name = (new FuzzyMatchService)->normalize($student->full_name);
        });
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class, 'student_id');
    }

    protected function getAuditAttributes(): array
    {
        return [
            'full_name' => $this->full_name,
            'normalized_name' => $this->normalized_name,
            'class_id' => $this->class_id,
        ];
    }
}
