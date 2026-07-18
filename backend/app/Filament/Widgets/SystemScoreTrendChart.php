<?php

namespace App\Filament\Widgets;

use App\Support\SystemStatsService;
use Filament\Widgets\LineChartWidget;

/**
 * Line chart: điểm TB toàn hệ thống theo tháng (dựa created_at của grade).
 */
class SystemScoreTrendChart extends LineChartWidget
{
    protected static ?int $sort = -9;

    protected static ?string $heading = 'Điểm trung bình toàn hệ thống theo tháng';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $rows = app(SystemStatsService::class)->scoreTrendByMonth();

        return [
            'datasets' => [
                [
                    'label' => 'Điểm TB',
                    'data' => array_map(fn ($r) => $r['avg'], $rows),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => '#fde68a',
                    'fill' => false,
                ],
            ],
            'labels' => array_map(fn ($r) => $r['month'], $rows),
        ];
    }
}
