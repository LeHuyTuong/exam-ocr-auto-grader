<?php

namespace App\Filament\Resources\SchoolClassResource\Widgets;

use App\Models\SchoolClass;
use App\Support\ClassStatsService;
use Filament\Widgets\Widget;

/**
 * Bảng "Học sinh cần hỗ trợ" — HS có >=1 kỹ năng dưới ngưỡng (điểm TB các lần Unit Test).
 * Widget tự build blade (dữ liệu computed, không phải query trực tiếp).
 */
class WeakStudentsTable extends Widget
{
    public ?SchoolClass $record = null;

    protected static string $view = 'filament.resources.school-class-resource.widgets.weak-students-table';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'students' => (new ClassStatsService($this->record))->weakStudents(),
        ];
    }
}
