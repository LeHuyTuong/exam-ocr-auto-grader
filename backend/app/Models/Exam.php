<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

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

    /**
     * Kích hoạt đề này làm đề đang hoạt động duy nhất của lớp, khoá (is_active=
     * false) mọi đề khác cùng lớp. Dùng ->each() thay vì bulk update để bắn model
     * event "updated" cho từng đề — trait Auditable cần event đó để ghi audit log
     * việc khoá/mở khoá (và is_active phải nằm trong getAuditAttributes()).
     *
     * Bọc trong DB::transaction ngay trong method (không phụ thuộc nơi gọi) vì có
     * 2 điểm gọi (route tạo đề mới + Action Filament) đều cần được bảo vệ như nhau:
     * nếu khoá xong đề khác nhưng activate chính nó thất bại, rollback để lớp không
     * bị trống active.
     */
    public function activateExclusively(): void
    {
        DB::transaction(function () {
            static::where('class_id', $this->class_id)
                ->where('id', '!=', $this->id)
                ->each(fn (self $e) => $e->update(['is_active' => false]));

            if (! $this->is_active) {
                $this->update(['is_active' => true]);
            }
        });
    }

    protected function getAuditAttributes(): array
    {
        return [
            'total_questions' => $this->total_questions,
            'max_score' => $this->max_score,
            'grading_mode' => $this->grading_mode,
            'class_id' => $this->class_id,
            'is_active' => $this->is_active,
        ];
    }
}
