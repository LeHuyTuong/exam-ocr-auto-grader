<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\Student;
use App\Services\FuzzyMatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        if (! $request->user()->isAdmin() && ! $request->user()->classes()->where('class_id', $classId)->exists()) {
            return response()->json(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền truy cập lớp này.'], 403);
        }

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

        $examId = $request->integer('exam_id');

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
        $grade = Grade::findOrFail($id);

        if (! $request->user()->isAdmin() && ! $request->user()->classes()->where('class_id', $grade->class_id)->exists()) {
            return response()->json(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền sửa điểm này.'], 403);
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

        if (! $request->user()->isAdmin() && ! $request->user()->classes()->where('class_id', $classId)->exists()) {
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
