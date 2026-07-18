<?php

namespace App\Filament\Resources\SchoolClassResource\Widgets;

use App\Models\SchoolClass;
use App\Support\ClassStatsService;
use Filament\Widgets\BarChartWidget;

class ClassSkillAveragesChart extends BarChartWidget
{
    public ?SchoolClass $record = null;

    protected static ?string $heading = 'Điểm TB theo kỹ năng (đỏ = dưới ngưỡng)';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $skills = (new ClassStatsService($this->record))->skillAverages();

        return [
            'datasets' => [
                [
                    'label' => 'Điểm TB',
                    'data' => array_values(array_map(fn ($s) => $s['avg'], $skills)),
                    'backgroundColor' => array_values(array_map(fn ($s) => $s['weak'] ? '#ef4444' : '#22c55e', $skills)),
                ],
            ],
            'labels' => array_values(array_map(fn ($s) => $s['label'].' (max '.$s['max'].')', $skills)),
        ];
    }
}
