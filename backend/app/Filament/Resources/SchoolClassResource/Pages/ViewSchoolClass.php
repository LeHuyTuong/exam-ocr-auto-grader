<?php

namespace App\Filament\Resources\SchoolClassResource\Pages;

use App\Filament\Resources\SchoolClassResource;
use App\Filament\Resources\SchoolClassResource\Widgets\ClassScoreTrendChart;
use App\Filament\Resources\SchoolClassResource\Widgets\ClassSkillAveragesChart;
use App\Filament\Resources\SchoolClassResource\Widgets\ClassStatsOverview;
use App\Filament\Resources\SchoolClassResource\Widgets\WeakStudentsTable;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSchoolClass extends ViewRecord
{
    protected static string $resource = SchoolClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ClassStatsOverview::class,
            ClassScoreTrendChart::class,
            ClassSkillAveragesChart::class,
            WeakStudentsTable::class,
        ];
    }
}
