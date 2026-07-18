<?php

namespace App\Filament\Actions;

use App\Models\Exam;
use App\Services\GradeExcelExporter;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Tables\Actions\Action;

/**
 * Nút "Xuất Excel" theo template 13 cột (có tự tính cột kỹ năng cần cải thiện).
 * Dùng chung cho GradeResource, ExamsRelationManager, GradesRelationManager.
 */
class ExportExamGradesAction
{
    /** Row action cho 1 bài thi ($record là Exam) — không cần chọn, xuất thẳng. */
    public static function rowAction(): Action
    {
        return Action::make('exportExamGrades')
            ->label('Xuất Excel')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Xuất điểm ra Excel')
            ->modalDescription('Tải file .xlsx theo template 13 cột (tự tính cột "kỹ năng cần cải thiện").')
            ->action(function (Exam $record, GradeExcelExporter $exporter) {
                return $exporter->downloadXlsxResponse($record);
            });
    }

    /**
     * Header action có ô chọn bài thi. Truyền $classIdResolver để lọc theo lớp
     * (dùng trong GradesRelationManager); bỏ qua để list toàn bộ bài thi.
     */
    public static function headerAction(?Closure $classIdResolver = null): Action
    {
        return Action::make('exportExamGrades')
            ->label('Xuất Excel')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->modalHeading('Xuất điểm ra Excel')
            ->modalDescription('Chọn bài thi để tải file .xlsx theo template 13 cột.')
            ->form(function () use ($classIdResolver) {
                $classId = $classIdResolver ? (int) ($classIdResolver)() : null;
                $options = Exam::query()
                    ->when($classId, fn ($q) => $q->where('class_id', $classId))
                    ->with('class')
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(fn ($e) => [$e->id => ($e->class?->code ? $e->class->code.' — ' : '').$e->name]);

                return [
                    Select::make('exam_id')
                        ->label('Bài thi')
                        ->options($options)
                        ->required()
                        ->searchable(),
                ];
            })
            ->action(function (array $data, GradeExcelExporter $exporter) {
                $exam = Exam::with('class')->findOrFail($data['exam_id']);

                return $exporter->downloadXlsxResponse($exam);
            });
    }
}
