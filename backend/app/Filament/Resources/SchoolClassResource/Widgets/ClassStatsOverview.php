<?php

namespace App\Filament\Resources\SchoolClassResource\Widgets;

use App\Models\SchoolClass;
use App\Support\ClassStatsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClassStatsOverview extends StatsOverviewWidget
{
    public ?SchoolClass $record = null;

    protected function getStats(): array
    {
        $svc = new ClassStatsService($this->record);
        $summary = $svc->trendSummary();
        $trend = $summary['trend'];

        $trendValue = $trend === null
            ? '—'
            : ($trend >= 0 ? "+{$trend}%" : "{$trend}%");
        $trendDesc = $trend === null
            ? 'Chưa đủ 2 bài đã chấm để so sánh'
            : ($trend >= 0 ? "Tăng {$trend}% so với bài thi trước" : 'Giảm '.abs((float) $trend).'% so với bài thi trước');
        $trendColor = $trend === null ? 'gray' : ($trend >= 0 ? 'success' : 'danger');

        return [
            Stat::make('Sĩ số', $svc->totalStudents())
                ->icon('heroicon-o-users'),
            Stat::make('Số bài thi', $svc->totalExams())
                ->icon('heroicon-o-document-text'),
            Stat::make('Điểm TB lớp', $svc->averageScore() !== null ? number_format((float) $svc->averageScore(), 2) : '—')
                ->icon('heroicon-o-academic-cap'),
            Stat::make('Xu hướng', $trendValue)
                ->icon($trend !== null && $trend >= 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($trendColor)
                ->description($trendDesc),
        ];
    }
}
