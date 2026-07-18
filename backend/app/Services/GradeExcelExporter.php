<?php

namespace App\Services;

use App\Models\Exam;
use App\Support\SkillAssessment;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

            // Cột TỔNG (max = 50): tổng 6 kỹ năng khi có sub_scores, fallback score.
            $total = SkillAssessment::totalFromSubScores($subScores) ?? $grade->score;
            $sheet->setCellValue([9, $row], $total);

            // Cột 10: tự tính các kỹ năng cần cải thiện theo ngưỡng 9/9/9/4/4/9
            // (tương đương TEXTJOIN trong công thức Excel của giáo viên).
            // Rỗng khi đạt hết hoặc khi chưa có điểm thành phần (bài đếm-câu-đúng).
            $sheet->setCellValue([10, $row], SkillAssessment::weakSkillsText($subScores));

            // Cột 11-13 (NHẬN XÉT / Nhóm tính cách / Nhận xét khi làm việc nhóm)
            // cố ý để trống cho giáo viên tự ghi.
        }

        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * Xuất file Excel theo template 13 cột và trả StreamedResponse để tải về.
     * Dùng chung cho API (ExamController) và nút "Xuất Excel" trong admin Filament.
     */
    public function downloadXlsxResponse(Exam $exam, ?string $filename = null): StreamedResponse
    {
        $spreadsheet = $this->export($exam);

        if ($filename === null) {
            $code = $exam->class?->code ?? 'class';
            $safeName = str_replace(['/', '\\', ' '], ['-', '-', '_'], (string) $exam->name);
            $filename = 'Diem_'.$code.'_'.$safeName.'_'.now()->format('Y-m-d').'.xlsx';
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
