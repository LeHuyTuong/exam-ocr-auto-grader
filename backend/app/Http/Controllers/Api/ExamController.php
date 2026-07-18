<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Services\GradeExcelExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamController extends Controller
{
    public function __construct(private GradeExcelExporter $exporter) {}

    public function today(Request $request): JsonResponse
    {
        $request->validate(['class_id' => 'required|exists:school_classes,id']);

        $classId = $request->integer('class_id');
        $schoolClass = SchoolClass::findOrFail($classId);

        if ($request->user()->cannot('view', $schoolClass)) {
            Log::warning('Access denied: exam.today', ['user_id' => $request->user()->id, 'class_id' => $classId, 'ip' => $request->ip()]);
            return response()->json(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền truy cập lớp này.'], 403);
        }

        $exam = Exam::where('class_id', $classId)->first();

        if (! $exam) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Chưa có bài thi cho lớp này.',
            ], 404);
        }

        return response()->json([
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
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
        $schoolClass = SchoolClass::findOrFail($classId);

        if ($request->user()->cannot('view', $schoolClass)) {
            Log::warning('Access denied: exam.today', ['user_id' => $request->user()->id, 'class_id' => $classId, 'ip' => $request->ip()]);
            return response()->json(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền truy cập lớp này.'], 403);
        }

        $existing = Exam::where('class_id', $classId)->first();

        if ($existing) {
            // Lớp đã có bài: cập nhật cấu hình tại chỗ (nhất là grading_mode) để
            // giáo viên đổi kiểu chấm ngay từ app. Trước đây trả về nguyên trạng
            // nên lỡ chọn nhầm kiểu chấm là kẹt, chỉ sửa được qua trang admin.
            $existing->update([
                'total_questions' => $request->integer('total_questions'),
                'max_score' => $request->input('max_score', $request->integer('total_questions')),
                'grading_mode' => $request->input('grading_mode', $existing->grading_mode),
            ]);

            return response()->json([
                'exam' => [
                    'id' => $existing->id,
                    'name' => $existing->name,
                    'totalQuestions' => $existing->total_questions,
                    'maxScore' => $existing->max_score,
                    'gradingMode' => $existing->grading_mode,
                ],
            ]);
        }

        $maxScore = $request->input('max_score', $request->integer('total_questions'));

        $exam = Exam::create([
            'class_id' => $classId,
            'name' => 'Bài thi '.$schoolClass->code,
            'total_questions' => $request->integer('total_questions'),
            'max_score' => $maxScore,
            'grading_mode' => $request->input('grading_mode', 'counting'),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'totalQuestions' => $exam->total_questions,
                'maxScore' => $exam->max_score,
                'gradingMode' => $exam->grading_mode,
            ],
        ], 201);
    }

    public function export(Request $request, int $id): StreamedResponse
    {
        $exam = Exam::with('class')->findOrFail($id);

        abort_unless($request->user()->can('view', $exam), 403, 'Bạn không có quyền xuất điểm lớp này.');

        return $this->exporter->downloadXlsxResponse($exam);
    }
}
