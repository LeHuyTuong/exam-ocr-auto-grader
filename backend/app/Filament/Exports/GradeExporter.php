<?php

namespace App\Filament\Exports;

use App\Models\Grade;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class GradeExporter extends Exporter
{
    protected static ?string $model = Grade::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('student.full_name')
                ->label('Họ tên'),
            ExportColumn::make('class.code')
                ->label('Mã lớp'),
            ExportColumn::make('class.name')
                ->label('Lớp'),
            ExportColumn::make('exam.date')
                ->label('Ngày thi'),
            ExportColumn::make('total_correct')
                ->label('Tổng câu đúng'),
            ExportColumn::make('score')
                ->label('Điểm'),
            ExportColumn::make('status')
                ->label('Trạng thái'),
            ExportColumn::make('created_at')
                ->label('Thời gian tạo'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Xuất Excel hoàn tất: '.number_format($export->successful_rows).' dòng.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ('.$failedRowsCount.' dòng lỗi.)';
        }

        return $body;
    }
}
