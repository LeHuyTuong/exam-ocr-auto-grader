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
        'sort_name',
        'aliases',
    ];

    protected function casts(): array
    {
        return [
            'aliases' => 'array',
        ];
    }

    /**
     * Hai khoá phái sinh, luôn tự tính lại từ full_name khi lưu:
     *
     * - normalized_name: tên viết thường, bỏ dấu — dùng để dò tên OCR. Trước đây
     *   form admin cho gõ tay và fallback về nguyên tên có dấu nên khoá bị sai.
     * - sort_name: khoá sắp xếp theo TÊN (xem sortKeyFor).
     */
    protected static function booted(): void
    {
        static::saving(function (self $student) {
            $student->normalized_name = (new FuzzyMatchService)->normalize($student->full_name);
            $student->sort_name = self::sortKeyFor($student->full_name);
        });
    }

    /**
     * Khoá sắp xếp danh sách lớp theo đúng cách gọi tên ở Việt Nam: xếp theo TÊN
     * (chữ cuối) chứ không phải họ — "Khổng Đinh Ngọc Hân" xếp ở vần H (Hân),
     * không phải vần K (Khổng). Trùng tên thì xét tiếp phần họ + tên đệm, nên
     * "Khổng ... Hân" đứng trước "Trần Ngọc Hân".
     *
     * Trả về dạng đã bỏ dấu, viết thường: "han khong dinh ngoc".
     */
    public static function sortKeyFor(?string $fullName): string
    {
        $normalized = (new FuzzyMatchService)->normalize($fullName);

        if ($normalized === '') {
            return '';
        }

        $parts = explode(' ', $normalized);
        $given = array_pop($parts);

        return trim($given.' '.implode(' ', $parts));
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
            'sort_name' => $this->sort_name,
            'class_id' => $this->class_id,
        ];
    }
}
