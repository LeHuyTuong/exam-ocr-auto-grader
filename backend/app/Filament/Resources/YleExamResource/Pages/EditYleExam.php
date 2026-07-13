<?php

namespace App\Filament\Resources\YleExamResource\Pages;

use App\Filament\Resources\YleExamResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditYleExam extends EditRecord
{
    protected static string $resource = YleExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
