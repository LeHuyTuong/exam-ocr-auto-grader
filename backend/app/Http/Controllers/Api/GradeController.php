<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\FuzzyMatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class GradeController extends Controller
{
    public function __construct(
        private FuzzyMatchService $fuzzyMatch,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'class_id' => 'required|exists:school_classes,id',
            'total_correct' => 'required|integer|min:0',
            'score' => 'required|numeric|min:0',
            'ocr_raw_name' => 'required|string|max:255',
            'image_url' => 'nullable|string',
            'image_url_2' => 'nullable|string',
            'ai_confidence' => 'nullable|numeric|min:0|max:1',
            'sub_scores' => 'nullable|array',
            'sub_scores.vocabulary' => 'nullable|integer|min:0',
            'sub_scores.grammar' => 'nullable|integer|min:0',
            'sub_scores.listening' => 'nullable|integer|min:0',
            'sub_scores.reading' => 'nullable|integer|min:0',
            'sub_scores.writing' => 'nullable|integer|min:0',
            'sub_scores.speaking' => 'nullable|integer|min:0',
            'student_id' => ['nullable', 'exists:students,id', Rule::exists('students', 'id')->where('class_id', $request->input('class_id'))],
            'create_new_student' => 'nullable|boolean',
            'new_student_name' => 'required_if:create_new_student,true|string|max:255',
        ]);

        $classId = $request->integer('class_id');
        $schoolClass = SchoolClass::findOrFail($classId);

        if ($request->user()->cannot('view', $schoolClass)) {
            Log::warning('Access denied: grades.store', ['user_id' => $request->user()->id, 'class_id' => $classId, 'ip' => $request->ip()]);
            return response()->json(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền truy cập lớp này.'], 403);
        }

        $examId = $request->integer('exam_id');
        $exam = Exam::findOrFail($examId);

        // Đề đã khoá (không active) — không cho chấm thêm. Kiểm sau quyền lớp để
        // người không thuộc lớp nhận FORBIDDEN chứ không suy ra được đề đang khoá.
        if (! $exam->is_active) {
            return response()->json([
                'error' => 'EXAM_LOCKED',
                'message' => 'Đề thi này đã khoá, không thể chấm thêm.',
            ], 403);
        }

        // Client gửi exam_id và class_id độc lập — trước giờ an toàn nhờ unique
        // (1 lớp = 1 exam), giờ nhiều đề/lớp phải cross-check để tránh gửi exam_id
        // của lớp khác kèm class_id lớp hiện tại.
        abort_unless($exam->class_id === $classId, 422, 'exam_id không khớp lớp.');

        // Chỉ sau khi qua hết quyền + khoá + cross-check mới tạo Student mới —
        // tránh tạo học sinh "mồ côi" rồi mới bị từ chối vì exam sai/khoá.
        $studentId = $request->input('student_id');

        if (! $studentId && $request->boolean('create_new_student')) {
            $name = $request->input('new_student_name');
            $student = Student::create([
                'class_id' => $classId,
                'full_name' => $name,
                'normalized_name' => $this->fuzzyMatch->normalize($name),
                'aliases' => [$request->input('ocr_raw_name')],
            ]);
            $studentId = $student->id;
        }

        if (! $studentId) {
            return response()->json([
                'error' => 'VALIDATION_ERROR',
                'message' => 'Thiếu student_id hoặc create_new_student.',
            ], 422);
        }

        // Không chặn chấm lại vĩnh viễn (1 học sinh có thể có nhiều bài kiểm
        // tra theo thời gian) — chỉ chặn chấm trùng trong 5 phút để tránh lỗi
        // thao tác (chụp/gửi nhầm 2 lần cùng 1 ảnh).
        $recentDuplicate = Grade::where('exam_id', $examId)
            ->where('student_id', $studentId)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->exists();

        if ($recentDuplicate) {
            return response()->json([
                'error' => 'DUPLICATE',
                'message' => 'Học sinh này vừa được chấm điểm trong 5 phút qua.',
            ], 409);
        }

        $grade = Grade::create([
            'exam_id' => $examId,
            'student_id' => $studentId,
            'class_id' => $classId,
            'total_correct' => $request->integer('total_correct'),
            'score' => $request->input('score'),
            'image_url' => $request->input('image_url'),
            'image_url_2' => $request->input('image_url_2'),
            'ai_confidence' => $request->input('ai_confidence'),
            'sub_scores' => $request->input('sub_scores'),
            'ocr_raw_name' => $request->input('ocr_raw_name'),
            'status' => 'confirmed',
            'confirmed_by' => $request->user()->id,
        ]);

        $grade->load('student');

        return response()->json([
            'grade' => [
                'id' => $grade->id,
                'studentName' => $grade->student->full_name,
                'totalCorrect' => $grade->total_correct,
                'score' => (float) $grade->score,
            ],
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $grade = Grade::with('exam')->findOrFail($id);

        if ($request->user()->cannot('update', $grade)) {
            Log::warning('Access denied: grades.update', ['user_id' => $request->user()->id, 'class_id' => $grade->class_id, 'ip' => $request->ip()]);
            return response()->json(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền sửa điểm này.'], 403);
        }

        // Đề đã khoá — không cho sửa điểm. Kiểm sau quyền (cannot('update')) để
        // người không thuộc lớp nhận FORBIDDEN chứ không suy ra được đề đang khoá.
        if (! $grade->exam->is_active) {
            return response()->json([
                'error' => 'EXAM_LOCKED',
                'message' => 'Đề thi này đã khoá, không thể chấm thêm.',
            ], 403);
        }

        $request->validate([
            'total_correct' => 'nullable|integer|min:0',
            'score' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:pending,confirmed',
        ]);

        $grade->update($request->only(['total_correct', 'score', 'status']));
        $grade->load('student');

        return response()->json([
            'grade' => [
                'id' => $grade->id,
                'studentName' => $grade->student->full_name,
                'totalCorrect' => $grade->total_correct,
                'score' => (float) $grade->score,
                'status' => $grade->status,
                'imageUrl' => $grade->image_url,
                'createdAt' => $grade->created_at,
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'class_id' => 'nullable|exists:school_classes,id',
            'student_id' => 'nullable|exists:students,id',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $exam = Exam::findOrFail($request->integer('exam_id'));
        $classId = $request->integer('class_id', $exam->class_id);
        $schoolClass = SchoolClass::findOrFail($classId);

        if ($request->user()->cannot('view', $schoolClass)) {
            Log::warning('Access denied: grades.index', ['user_id' => $request->user()->id, 'class_id' => $classId, 'ip' => $request->ip()]);
            return response()->json(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền xem điểm lớp này.'], 403);
        }

        $query = Grade::with('student')
            ->where('exam_id', $request->integer('exam_id'));

        if ($request->has('class_id')) {
            $query->where('class_id', $classId);
        }

        if ($request->has('student_id')) {
            $query->where('student_id', $request->integer('student_id'));
        }

        $perPage = $request->integer('per_page', 15);
        $grades = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'grades' => collect($grades->items())->map(fn ($g) => [
                'id' => $g->id,
                'studentName' => $g->student->full_name,
                'totalCorrect' => $g->total_correct,
                'score' => (float) $g->score,
                'status' => $g->status,
                'imageUrl' => $g->image_url,
                'createdAt' => $g->created_at,
            ]),
            'meta' => [
                'current_page' => $grades->currentPage(),
                'last_page' => $grades->lastPage(),
                'per_page' => $grades->perPage(),
                'total' => $grades->total(),
            ],
        ]);
    }
}
