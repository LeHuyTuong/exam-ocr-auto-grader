<?php

namespace App\Services;

use App\Models\Exam;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class GradeExcelExporter
{
    private const HEADERS = [
        'NO',
        'NAME',
        "TỪ VỰNG\n(max = 10)",
        "NGỮ PHÁP\n(max = 10)",
        "NGHE\n(max = 10)",
        "ĐỌC\n(max = 5)",
        "VIẾT\n(max = 5)",
        "NÓI\n(max = 10)",
        "TỔNG\n(max = 50)",
        "CÁC KỸ NĂNG CẦN CẢI THIỆN\n(nếu đạt thì để trống)",
        'NHẬN XÉT',
        'Nhóm tính cách',
        'Nhận xét khi làm việc nhóm',
    ];

    /** Order matches the C-H columns above. */
    private const SUB_SCORE_KEYS = ['vocabulary', 'grammar', 'listening', 'reading', 'writing', 'speaking'];

    public function export(Exam $exam): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach (self::HEADERS as $i => $header) {
            $sheet->setCellValue([$i + 1, 1], $header);
        }
        $sheet->getStyle('A1:M1')->getFont()->setBold(true);
        $sheet->getStyle('A1:M1')->getAlignment()->setWrapText(true);

        $grades = $exam->grades()->with('student')->get()
            ->sortBy(fn ($g) => $g->student?->full_name ?? '')
            ->values();

        foreach ($grades as $i => $grade) {
            $row = $i + 2;
            $subScores = $grade->sub_scores ?? [];

            $sheet->setCellValue([1, $row], $i + 1);
            $sheet->setCellValue([2, $row], $grade->student?->full_name);

            foreach (self::SUB_SCORE_KEYS as $j => $key) {
                $sheet->setCellValue([3 + $j, $row], $subScores[$key] ?? null);
            }

            $sheet->setCellValue([9, $row], $grade->score);
            // Cột 10-13 (nhận xét/tính cách) cố ý để trống cho giáo viên tự ghi.
        }

        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }
}
