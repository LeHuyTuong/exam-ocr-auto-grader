<?php

namespace App\Filament\Widgets;

use App\Support\SystemStatsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Widget tổng quan hệ thống (dashboard chính): số lớp, số HS, số bài đã chấm,
 * % cải thiện điểm toàn hệ thống.
 */
class SystemStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -10;

    protected function getStats(): array
    {
        $svc = app(SystemStatsService::class);
        $improvement = $svc->improvementPercent();

        $improvementValue = $improvement === null
            ? '—'
            : ($improvement >= 0 ? "+{$improvement}%" : "{$improvement}%");
        $improvementDesc = $improvement === null
            ? 'Chưa đủ dữ liệu (mỗi lớp cần >= 2 bài đã chấm)'
            : ($improvement >= 0 ? "Điểm TB toàn hệ thống tăng {$improvement}%" : 'Điểm TB toàn hệ thống giảm '.abs((float) $improvement).'%');
        $improvementColor = $improvement === null ? 'gray' : ($improvement >= 0 ? 'success' : 'danger');

        return [
            Stat::make('Tổng số lớp', $svc->totalClasses())
                ->icon('heroicon-o-rectangle-stack'),
            Stat::make('Tổng học sinh', $svc->totalStudents())
                ->icon('heroicon-o-users'),
            Stat::make('Bài đã chấm', $svc->totalGraded())
                ->icon('heroicon-o-document-check'),
            Stat::make('Cải thiện điểm', $improvementValue)
                ->icon($improvement !== null && $improvement >= 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($improvementColor)
                ->description($improvementDesc),
        ];
    }
}
