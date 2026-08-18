<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Student;
use App\Support\SkillAssessment;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
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

    /**
     * Định dạng số cho các cột điểm: nguyên hiện "8", lẻ hiện "7.5".
     *
     * PHẢI là General, không được dùng mã kiểu "0.##": Excel luôn vẽ dấu phân
     * cách thập phân có trong mã kể cả khi phần lẻ rỗng, nên điểm 10 hiện ra
     * "10." — và bản Excel tiếng Việt đổi dấu chấm đó thành dấu phẩy thành
     * "10,". General không có dấu nào cứng nên số nguyên hiện gọn "10".
     */
    private const SCORE_FORMAT = NumberFormat::FORMAT_GENERAL;

    public function export(Exam $exam): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach (self::HEADERS as $i => $header) {
            $sheet->setCellValue([$i + 1, 1], $header);
        }
        $sheet->getStyle('A1:M1')->getFont()->setBold(true);
        $sheet->getStyle('A1:M1')->getAlignment()->setWrapText(true);

        // Sắp theo TÊN (chữ cuối) như danh sách lớp ở Việt Nam, đã bỏ dấu:
        // "Khổng Đinh Ngọc Hân" nằm ở vần H (Hân) chứ không phải vần K.
        $grades = $exam->grades()->with('student')->get()
            ->sortBy(fn ($g) => self::sortKey($g->student?->full_name, $g->student?->sort_name), SORT_NATURAL)
            ->values();

        foreach ($grades as $i => $grade) {
            $row = $i + 2;
            $subScores = $grade->sub_scores ?? [];

            $sheet->setCellValue([1, $row], $i + 1);
            $sheet->setCellValue([2, $row], $grade->student?->full_name);

            foreach (self::SUB_SCORE_KEYS as $j => $key) {
                $this->setScoreCell($sheet, 3 + $j, $row, SkillAssessment::toScore($subScores[$key] ?? null));
            }

            // Cột TỔNG (max = 50): tổng 6 kỹ năng khi có sub_scores, fallback score.
            $total = SkillAssessment::totalFromSubScores($subScores) ?? SkillAssessment::toScore($grade->score);
            $this->setScoreCell($sheet, 9, $row, $total);

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
     * Ghi 1 ô điểm dưới dạng SỐ thật. Trước đây đẩy thẳng giá trị từ JSON vào
     * setCellValue: điểm lưu dạng chuỗi ("7.5"/"7,5") bị Excel bản tiếng Việt
     * coi là text nên bôi đen cả cột cũng không SUM ra tổng.
     */
    private function setScoreCell(Worksheet $sheet, int $col, int $row, ?float $score): void
    {
        if ($score === null) {
            return;
        }

        // Chốt 2 chữ số thập phân trước khi ghi: General hiện đúng những gì có
        // trong ô, mà cộng dồn float có thể ra 43.499999999 — làm tròn ở đây để
        // không hiện ra một dãy số lẻ dài trong file gửi phụ huynh.
        $sheet->setCellValueExplicit([$col, $row], round($score, 2), DataType::TYPE_NUMERIC);
        $sheet->getStyle([$col, $row, $col, $row])
            ->getNumberFormat()
            ->setFormatCode(self::SCORE_FORMAT);
    }

    /** Khoá sắp xếp theo tên; tính lại từ full_name khi cột sort_name còn trống. */
    private static function sortKey(?string $fullName, ?string $sortName): string
    {
        if ($sortName !== null && $sortName !== '') {
            return $sortName;
        }

        return Student::sortKeyFor($fullName);
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
