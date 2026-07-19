<?php

namespace App\Filament\Resources\ExamResource\Pages;

use App\Filament\Resources\ExamResource;
use App\Models\Exam;
use Filament\Resources\Pages\CreateRecord;

class CreateExam extends CreateRecord
{
    protected static string $resource = ExamResource::class;

    protected function afterCreate(): void
    {
        // Đây là lối tạo đề thứ 2 (menu "Bài thi" top-level, ngoài
        // ExamsRelationManager trong trang lớp) — vẫn phải giữ bất biến
        // "đúng 1 đề active/lớp", nếu không sẽ tạo ra 2 đề cùng active.
        /** @var Exam $exam */
        $exam = $this->record;
        $exam->activateExclusively();
    }
}
