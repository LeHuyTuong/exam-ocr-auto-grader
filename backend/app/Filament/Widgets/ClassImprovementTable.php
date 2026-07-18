<?php

namespace App\Filament\Widgets;

use App\Support\SystemStatsService;
use Filament\Widgets\Widget;

/**
 * Bảng xếp hạng lớp theo % tiến bộ (bài mới nhất vs bài liền trước).
 */
class ClassImprovementTable extends Widget
{
    protected static ?int $sort = -8;

    protected static string $view = 'filament.widgets.class-improvement-table';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'classes' => app(SystemStatsService::class)->classImprovementRanking(),
        ];
    }
}
