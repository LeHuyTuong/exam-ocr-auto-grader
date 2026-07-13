<?php

namespace App\Filament\Resources\YleSubmissionResource\Pages;

use App\Filament\Resources\YleSubmissionResource;
use App\Models\Yle\YleAnswer;
use App\Models\Yle\YleSubmission;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;

class ViewYleSubmission extends ViewRecord
{
    protected static string $resource = YleSubmissionResource::class;

    protected function getHeaderTitle(): string
    {
        $record = $this->getRecord();

        return "Kết quả: {$record->student?->full_name} - {$record->exam?->name}";
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        $data['yle_exam_id'] = $record->yle_exam_id;
        $data['student_id'] = $record->student_id;
        $data['auto_score'] = $record->auto_score;
        $data['manual_score'] = $record->manual_score;
        $data['total_score'] = $record->total_score;
        $data['max_score'] = $record->max_score;
        $data['status'] = $record->status;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        return [
            Action::make('manualGrade')
                ->label('Chấm tay')
                ->icon('heroicon-o-pencil-square')
                ->form(fn () => $this->getManualGradeForm())
                ->action(fn (array $data) => $this->saveManualGrade($data))
                ->modalWidth('lg')
                ->visible(fn (): bool => $record->exam->parts()->where('is_auto_gradable', false)->exists()),
        ];
    }

    private function getManualGradeForm(): array
    {
        $record = $this->getRecord();
        $parts = $record->exam->parts()->where('is_auto_gradable', false)->get();
        $fields = [];

        foreach ($parts as $part) {
            $page = $record->pages->firstWhere('page_number', $part->page_number);

            if ($page?->image_url) {
                $fields[] = Forms\Components\Placeholder::make("image_{$part->id}")
                    ->label("Ảnh bài làm - {$part->title}")
                    ->content(fn () => new HtmlString(
                        "<img src=\"{$page->image_url}\" style=\"max-width:100%;max-height:280px;border-radius:8px;margin-bottom:8px\">"
                    ));
            }

            $fields[] = Forms\Components\TextInput::make("marks_{$part->id}")
                ->label("{$part->title} (tối đa {$part->max_marks} điểm)")
                ->numeric()
                ->minValue(0)
                ->maxValue($part->max_marks)
                ->default(0)
                ->required();
        }

        if (empty($fields)) {
            $fields[] = Forms\Components\Placeholder::make('no_manual_parts')
                ->label('')
                ->content('Không có phần nào cần chấm tay.');
        }

        return $fields;
    }

    private function saveManualGrade(array $data): void
    {
        $record = $this->getRecord();
        $totalManual = 0;

        $parts = $record->exam->parts()->where('is_auto_gradable', false)->get();

        foreach ($parts as $part) {
            $key = "marks_{$part->id}";
            $marks = isset($data[$key]) ? max(0, min((int) $data[$key], $part->max_marks)) : 0;
            $totalManual += $marks;

            foreach ($part->questions as $question) {
                YleAnswer::updateOrCreate(
                    [
                        'yle_submission_id' => $record->id,
                        'yle_question_id' => $question->id,
                    ],
                    [
                        'graded_by' => 'manual',
                        'is_correct' => null,
                        'marks_awarded' => 0,
                    ]
                );
            }
        }

        $record->update(['manual_score' => $totalManual]);

        $autoScore = YleAnswer::where('yle_submission_id', $record->id)
            ->where('graded_by', 'auto')
            ->sum('marks_awarded');

        $totalScore = $autoScore + $totalManual;

        $hasNeedsReview = YleAnswer::where('yle_submission_id', $record->id)
            ->where('graded_by', 'auto')
            ->where('ai_confidence', '<', 0.6)
            ->exists();

        $status = $hasNeedsReview ? 'needs_review' : 'completed';

        $record->update([
            'auto_score' => $autoScore,
            'total_score' => $totalScore,
            'status' => $status,
        ]);

        $this->refreshFormData([
            'auto_score', 'manual_score', 'total_score', 'status',
        ]);

        $this->dispatch('notify', type: 'success', body: 'Đã lưu điểm chấm tay.');
    }
}
