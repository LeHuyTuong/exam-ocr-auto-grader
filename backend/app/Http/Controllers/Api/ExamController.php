<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Services\GradeExcelExporter;
use Carbon\Carbon;
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

        $exam = Exam::where('class_id', $classId)->where('is_active', true)->first();

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

        $existing = Exam::where('class_id', $classId)->where('is_active', true)->first();

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

    /**
     * Danh sách đề của 1 lớp (mọi đề, kể cả đã khoá), sắp created_at desc — cho
     * mobile chọn đề trước khi quét / xem lại đề cũ.
     */
    public function index(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        if ($request->user()->cannot('view', $schoolClass)) {
            Log::warning('Access denied: exams.index', ['user_id' => $request->user()->id, 'class_id' => $schoolClass->id, 'ip' => $request->ip()]);
            return response()->json(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền truy cập lớp này.'], 403);
        }

        $exams = $schoolClass->exams()
            ->orderBy('created_at', 'desc')
            ->get(['id', 'name', 'grading_mode', 'total_questions', 'max_score', 'is_active', 'created_at']);

        return response()->json([
            'exams' => $exams->map(fn (Exam $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'gradingMode' => $e->grading_mode,
                'totalQuestions' => $e->total_questions,
                'maxScore' => $e->max_score,
                'isActive' => $e->is_active,
                'createdAt' => $e->created_at,
            ]),
        ]);
    }

    /**
     * Tạo đề mới cho lớp — đề mới thành đề active duy nhất, mọi đề cũ bị khoá.
     * Khi client không gửi name, server tự sinh "Bài thi {code} - {d/m/Y}" (luôn
     * tính ngày ở server, không tin name client gửi có mang ngày hay không).
     */
    public function store(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'total_questions' => 'required|integer|min:1|max:500',
            'max_score' => 'nullable|integer|min:1|max:100',
            'grading_mode' => 'nullable|in:counting,graded',
        ]);

        if ($request->user()->cannot('view', $schoolClass)) {
            Log::warning('Access denied: exams.store', ['user_id' => $request->user()->id, 'class_id' => $schoolClass->id, 'ip' => $request->ip()]);
            return response()->json(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền truy cập lớp này.'], 403);
        }

        $name = $request->filled('name')
            ? $request->input('name')
            : 'Bài thi '.$schoolClass->code.' - '.Carbon::now()->format('d/m/Y');

        $exam = Exam::create([
            'class_id' => $schoolClass->id,
            'name' => $name,
            'total_questions' => $request->integer('total_questions'),
            'max_score' => $request->input('max_score', $request->integer('total_questions')),
            'grading_mode' => $request->input('grading_mode', 'counting'),
            'created_by' => $request->user()->id,
            'is_active' => true,
        ]);

        // Khoá mọi đề cũ của lớp, đề vừa tạo thành đề active duy nhất. Method tự
        // bọc DB::transaction nên không cần bọc thêm ở đây.
        $exam->activateExclusively();

        return response()->json([
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'totalQuestions' => $exam->total_questions,
                'maxScore' => $exam->max_score,
                'gradingMode' => $exam->grading_mode,
                'isActive' => $exam->is_active,
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
