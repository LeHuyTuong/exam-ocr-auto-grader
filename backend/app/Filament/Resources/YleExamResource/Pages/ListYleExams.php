<?php

namespace App\Filament\Resources\YleExamResource\Pages;

use App\Filament\Resources\YleExamResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListYleExams extends ListRecords
{
    protected static string $resource = YleExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
