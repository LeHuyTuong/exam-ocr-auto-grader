<?php

namespace App\Filament\Exports;

use App\Models\Yle\YleSubmission;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class YleSubmissionExporter extends Exporter
{
    protected static ?string $model = YleSubmission::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('exam.name')
                ->label('Bài thi'),
            ExportColumn::make('exam.level')
                ->label('Cấp độ'),
            ExportColumn::make('exam.skill')
                ->label('Kỹ năng'),
            ExportColumn::make('student.full_name')
                ->label('Học sinh'),
            ExportColumn::make('class.code')
                ->label('Mã lớp'),
            ExportColumn::make('class.name')
                ->label('Lớp'),
            ExportColumn::make('auto_score')
                ->label('Điểm tự động'),
            ExportColumn::make('manual_score')
                ->label('Điểm chấm tay'),
            ExportColumn::make('total_score')
                ->label('Tổng điểm'),
            ExportColumn::make('max_score')
                ->label('Điểm tối đa'),
            ExportColumn::make('status')
                ->label('Trạng thái'),
            ExportColumn::make('exam_date')
                ->label('Ngày thi'),
            ExportColumn::make('created_at')
                ->label('Thời gian tạo'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Xuất Excel hoàn tất: ' . number_format($export->successful_rows) . ' dòng.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' (' . $failedRowsCount . ' dòng lỗi.)';
        }

        return $body;
    }
}
