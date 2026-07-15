<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Services\GradeExcelExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamController extends Controller
{
    public function __construct(private GradeExcelExporter $exporter) {}

    public function today(Request $request): JsonResponse
    {
        $request->validate(['class_id' => 'required|exists:school_classes,id']);

        $classId = $request->integer('class_id');

        if (! $request->user()->isAdmin() && ! $request->user()->classes()->where('class_id', $classId)->exists()) {
            return response()->json(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền truy cập lớp này.'], 403);
        }

        $exam = Exam::where('class_id', $classId)
            ->where('date', today())
            ->first();

        if (! $exam) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Chưa có bài thi hôm nay cho lớp này.',
            ], 404);
        }

        return response()->json([
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'date' => $exam->date->format('Y-m-d'),
                'totalQuestions' => $exam->total_questions,
                'maxScore' => $exam->max_score,
                'gradingMode' => $exam->grading_mode,
            ],
        ]);
    }

    public function storeToday(Request $request): JsonResponse
    {
        $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'total_questions' => 'required|integer|min:1|max:500',
            'max_score' => 'nullable|integer|min:1|max:100',
            'grading_mode' => 'nullable|in:counting,graded',
        ]);

        $classId = $request->integer('class_id');

        if (! $request->user()->isAdmin() && ! $request->user()->classes()->where('class_id', $classId)->exists()) {
            return response()->json(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền truy cập lớp này.'], 403);
        }

        $existing = Exam::where('class_id', $classId)
            ->where('date', today())
            ->first();

        if ($existing) {
            return response()->json([
                'exam' => [
                    'id' => $existing->id,
                    'name' => $existing->name,
                    'date' => $existing->date->format('Y-m-d'),
                    'totalQuestions' => $existing->total_questions,
                    'maxScore' => $existing->max_score,
                    'gradingMode' => $existing->grading_mode,
                ],
            ]);
        }

        $class = SchoolClass::findOrFail($classId);
        $maxScore = $request->input('max_score', $request->integer('total_questions'));

        $exam = Exam::create([
            'class_id' => $classId,
            'name' => 'Bài thi '.$class->code.' - '.today()->format('d/m/Y'),
            'date' => today(),
            'total_questions' => $request->integer('total_questions'),
            'max_score' => $maxScore,
            'grading_mode' => $request->input('grading_mode', 'counting'),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'date' => $exam->date->format('Y-m-d'),
                'totalQuestions' => $exam->total_questions,
                'maxScore' => $exam->max_score,
                'gradingMode' => $exam->grading_mode,
            ],
        ], 201);
    }

    public function export(Request $request, int $id): StreamedResponse
    {
        $exam = Exam::with('class')->findOrFail($id);

        abort_unless(
            $request->user()->isAdmin() || $request->user()->classes()->where('class_id', $exam->class_id)->exists(),
            403,
            'Bạn không có quyền xuất điểm lớp này.'
        );

        $spreadsheet = $this->exporter->export($exam);
        // Tên exam có thể chứa ngày dạng "d/m/Y" (vd "Bài thi ... - 15/07/2026")
        // nên phải bỏ ký tự "/" và "\" trước khi dùng làm tên file.
        $safeName = str_replace(['/', '\\', ' '], ['-', '-', '_'], $exam->name);
        $filename = 'Diem_'.$exam->class->code.'_'.$safeName.'_'.$exam->date->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
