<?php

namespace App\Filament\Resources\YleExamResource\Pages;

use App\Filament\Resources\YleExamResource;
use App\Support\YleTemplates;
use Filament\Resources\Pages\CreateRecord;

class CreateYleExam extends CreateRecord
{
    protected static string $resource = YleExamResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        $template = YleTemplates::get($data['level'], $data['skill']);
        if ($template) {
            $data['total_marks'] = $template['total_marks'];
            $data['total_pages'] = $template['total_pages'];
            if (empty($data['name'])) {
                $data['name'] = $template['name'];
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        // Scaffold parts/questions from template if Repeater was left empty
        if ($record->parts()->count() === 0) {
            $template = YleTemplates::get($record->level, $record->skill);
            if ($template) {
                foreach ($template['parts'] as $partData) {
                    $questions = $partData['questions'] ?? [];
                    unset($partData['questions']);
                    $part = $record->parts()->create($partData);
                    foreach ($questions as $qData) {
                        $part->questions()->create($qData);
                    }
                }
                $record->load('parts.questions');
            }
        }
    }
}
