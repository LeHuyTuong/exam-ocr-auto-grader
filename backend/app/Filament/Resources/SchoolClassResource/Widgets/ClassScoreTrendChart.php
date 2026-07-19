<?php

namespace App\Filament\Resources\SchoolClassResource\Widgets;

use App\Models\SchoolClass;
use App\Support\ClassStatsService;
use Filament\Widgets\LineChartWidget;

class ClassScoreTrendChart extends LineChartWidget
{
    public ?SchoolClass $record = null;

    protected static ?string $heading = 'Điểm trung bình qua từng bài thi';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $sessions = (new ClassStatsService($this->record))->averageScoreByExam();

        return [
            'datasets' => [
                [
                    'label' => 'Điểm TB',
                    'data' => array_map(fn ($s) => $s['avg'], $sessions),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => '#fde68a',
                    'fill' => false,
                ],
            ],
            'labels' => array_map(fn ($s) => $s['examName'], $sessions),
        ];
    }
}
