<?php

namespace App\Http\Controllers\Api\Yle;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Yle\YleAnswer;
use App\Models\Yle\YlePart;
use App\Models\Yle\YleQuestion;
use App\Models\Yle\YleSubmission;
use App\Models\Yle\YleSubmissionPage;
use App\Services\CloudinaryService;
use App\Services\FuzzyMatchService;
use App\Services\Vision\AnswerSheetExtractor;
use App\Services\YleGradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class YleSubmissionController extends Controller
{
    public function __construct(
        private AnswerSheetExtractor $extractor,
        private CloudinaryService $cloudinary,
        private FuzzyMatchService $fuzzyMatch,
        private YleGradingService $grading,
    ) {}

    private function authorizeAccess(YleSubmission $submission, \Illuminate\Http\Request $request): bool
    {
        if ($request->user()->isAdmin()) {
            return true;
        }
        return $request->user()->classes()->where('class_id', $submission->class_id)->exists();
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'yle_exam_id' => 'required|exists:yle_exams,id',
            'class_id' => 'required|exists:school_classes,id',
            'exam_date' => 'required|date',
        ]);

        $classId = $request->integer('class_id');

        if (! $request->user()->isAdmin() && ! $request->user()->classes()->where('class_id', $classId)->exists()) {
            return response()->json(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền truy cập lớp này.'], 403);
        }

        $submission = YleSubmission::create([
            'yle_exam_id' => $request->integer('yle_exam_id'),
            'class_id' => $classId,
            'exam_date' => $request->input('exam_date'),
            'max_score' => YlePart::where('yle_exam_id', $request->integer('yle_exam_id'))->sum('max_marks'),
            'status' => 'pending',
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'submission' => $this->formatSubmission($submission),
        ], 201);
    }

    public function uploadPage(Request $request, int $id): JsonResponse
    {
        $submission = YleSubmission::findOrFail($id);

        if (! $this->authorizeAccess($submission, $request)) {
            return response()->json(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền truy cập submission này.'], 403);
        }

        $request->validate([
            'image' => 'required|image|max:10240',
            'page_number' => [
                'required', 'integer', 'min:1',
                Rule::unique('yle_submission_pages', 'page_number')
                    ->where('yle_submission_id', $id),
            ],
        ]);

        $pageNumber = $request->integer('page_number');

        // Lưu ảnh lên Cloudinary — nếu chưa cấu hình / lỗi mạng thì vẫn cho chấm tiếp,
        // chỉ mất ảnh minh hoạ (giống OcrController), không để 500.
        $imageUrl = null;
        try {
            $imageUrl = $this->cloudinary->upload(
                file_get_contents($request->file('image')->getRealPath()),
                "yle/{$submission->yle_exam_id}/{$id}"
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('YLE cloudinary upload failed', [
                'submission_id' => $id,
                'page_number' => $pageNumber,
                'error' => $e->getMessage(),
            ]);
        }

        $page = YleSubmissionPage::create([
            'yle_submission_id' => $id,
            'page_number' => $pageNumber,
            'image_url' => $imageUrl,
        ]);

        // Xử lý AI/chấm điểm — nếu lỗi vẫn trả về trang đã lưu để không chặn quy trình.
        try {
            $result = $this->processPage($submission, $page, $request);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('YLE processPage failed', [
                'submission_id' => $id,
                'page_number' => $pageNumber,
                'error' => $e->getMessage(),
            ]);
            $result = ['answers' => [], 'candidates' => []];
        }

        return response()->json([
            'page' => [
                'id' => $page->id,
                'pageNumber' => $page->page_number,
                'imageUrl' => $page->image_url,
            ],
            'answers' => $result['answers'],
            'studentNameCandidates' => $result['candidates'],
            'ocrRawName' => $submission->fresh()->ocr_raw_name,
        ]);
    }

    public function updateStudent(Request $request, int $id): JsonResponse
    {
        $submission = YleSubmission::findOrFail($id);

        if (! $this->authorizeAccess($submission, $request)) {
            return response()->json(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền truy cập submission này.'], 403);
        }

        $request->validate([
            'student_id' => ['nullable', 'exists:students,id', Rule::exists('students', 'id')->where('class_id', $submission->class_id)],
            'create_new_student' => 'nullable|boolean',
            'new_student_name' => 'required_if:create_new_student,true|string|max:255',
        ]);

        $studentId = $request->input('student_id');

        if (! $studentId && $request->boolean('create_new_student')) {
            $name = $request->input('new_student_name');
            $student = Student::create([
                'class_id' => $submission->class_id,
                'full_name' => $name,
                'normalized_name' => $this->fuzzyMatch->normalize($name),
                'aliases' => [$submission->ocr_raw_name],
            ]);
            $studentId = $student->id;
        }

        if (! $studentId) {
            return response()->json([
                'error' => 'VALIDATION_ERROR',
                'message' => 'Thiếu student_id hoặc create_new_student.',
            ], 422);
        }

        // Check duplicate for this exam+student
        $exists = YleSubmission::where('yle_exam_id', $submission->yle_exam_id)
            ->where('student_id', $studentId)
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => 'DUPLICATE',
                'message' => 'Học sinh này đã có submission cho bài thi này.',
            ], 409);
        }

        $submission->update(['student_id' => $studentId]);

        return response()->json([
            'submission' => $this->formatSubmission($submission->fresh()),
        ]);
    }

    public function addManualMarks(Request $request, int $id): JsonResponse
    {
        $submission = YleSubmission::findOrFail($id);

        if (! $this->authorizeAccess($submission, $request)) {
            return response()->json(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền truy cập submission này.'], 403);
        }

        $request->validate([
            'marks' => 'required|array',
            'marks.*.part_id' => [
                'required',
                Rule::exists('yle_parts', 'id')->where('yle_exam_id', $submission->yle_exam_id),
            ],
            'marks.*.marks' => 'required|integer|min:0',
        ]);

        $totalManual = 0;

        foreach ($request->input('marks') as $item) {
            $part = YlePart::withCount('questions')->findOrFail($item['part_id']);
            $marks = min($item['marks'], $part->max_marks);
            $totalManual += $marks;

            $questions = $part->questions()->orderBy('question_number')->get();

            foreach ($questions as $i => $question) {
                // Record per-question marks without guessing which specific ones are correct
                YleAnswer::updateOrCreate(
                    [
                        'yle_submission_id' => $id,
                        'yle_question_id' => $question->id,
                    ],
                    [
                        'graded_by' => 'manual',
                        'is_correct' => null,
                        'marks_awarded' => 0,
                    ]
                );
            }
        }

        $submission->update([
            'manual_score' => $totalManual,
        ]);

        $this->recalculateTotals($submission);

        // If all pages uploaded, set to completed
        $totalPages = $submission->exam->total_pages;
        $uploadedPages = YleSubmissionPage::where('yle_submission_id', $id)->count();
        if ($uploadedPages >= $totalPages && $submission->status !== 'needs_review') {
            $submission->update(['status' => 'completed']);
        }

        return response()->json([
            'submission' => $this->formatSubmission($submission->fresh()),
        ]);
    }

    public function updateAnswer(Request $request, int $id): JsonResponse
    {
        $answer = YleAnswer::with('submission')->findOrFail($id);

        if (! $this->authorizeAccess($answer->submission, $request)) {
            return response()->json(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền truy cập submission này.'], 403);
        }

        $request->validate([
            'student_answer' => 'nullable|string|max:255',
            'is_correct' => 'nullable|boolean',
            'marks_awarded' => 'nullable|integer|min:0',
        ]);

        $answer->update([
            'student_answer' => $request->input('student_answer', $answer->student_answer),
            'is_correct' => $request->input('is_correct', $answer->is_correct),
            'marks_awarded' => $request->input('marks_awarded', $answer->marks_awarded),
            'graded_by' => 'manual',
        ]);

        $this->recalculateTotals($answer->submission);

        return response()->json([
            'answer' => $this->formatAnswer($answer),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $submission = YleSubmission::with([
            'exam.parts.questions',
            'pages',
            'answers.question',
            'student',
        ])->findOrFail($id);

        if (! $this->authorizeAccess($submission, $request)) {
            return response()->json(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền xem submission này.'], 403);
        }

        $breakdown = $this->buildBreakdown($submission);

        return response()->json([
            'submission' => $this->formatSubmission($submission),
            'breakdown' => $breakdown,
            'pages' => $submission->pages->map(fn ($p) => [
                'id' => $p->id,
                'pageNumber' => $p->page_number,
                'imageUrl' => $p->image_url,
            ]),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'yle_exam_id' => 'nullable|exists:yle_exams,id',
            'class_id' => 'nullable|exists:school_classes,id',
            'student_id' => 'nullable|exists:students,id',
            'status' => 'nullable|in:pending,grading,auto_graded,completed,needs_review',
        ]);

        $query = YleSubmission::with(['exam', 'student']);

        if ($request->has('yle_exam_id')) {
            $query->where('yle_exam_id', $request->integer('yle_exam_id'));
        }

        if ($request->has('class_id')) {
            $classId = $request->integer('class_id');
            if (! $request->user()->isAdmin() && ! $request->user()->classes()->where('class_id', $classId)->exists()) {
                return response()->json(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền xem lớp này.'], 403);
            }
            $query->where('class_id', $classId);
        }

        if ($request->has('student_id')) {
            $query->where('student_id', $request->integer('student_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = $request->integer('per_page', 15);
        $submissions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'submissions' => collect($submissions->items())->map(fn ($s) => $this->formatSubmission($s)),
            'meta' => [
                'current_page' => $submissions->currentPage(),
                'last_page' => $submissions->lastPage(),
                'per_page' => $submissions->perPage(),
                'total' => $submissions->total(),
            ],
        ]);
    }

    private function processPage(YleSubmission $submission, YleSubmissionPage $page, Request $request): array
    {
        $exam = $submission->exam;
        $pageNumber = $page->page_number;

        // Build pageSpec from ALL parts on this page (AI needs full structure)
        $partsOnPage = YlePart::with('questions')
            ->where('yle_exam_id', $exam->id)
            ->where('page_number', $pageNumber)
            ->orderBy('sort_order')
            ->get();

        $pageSpec = [
            'pageNumber' => $pageNumber,
            'parts' => $partsOnPage->map(fn ($part) => [
                'partNumber' => $part->part_number,
                'questionType' => $part->question_type,
                'questions' => $part->questions->map(fn ($q) => [
                    'questionNumber' => $q->question_number,
                ]),
            ])->toArray(),
        ];

        $hasAutoParts = $partsOnPage->where('is_auto_gradable', true)->isNotEmpty();

        $imagePath = $request->file('image')->getRealPath();
        $imageBytes = file_get_contents($imagePath);

        $result = null;
        $candidates = [];
        $returnedAnswers = [];

        // Call AI when page has auto parts OR it's page 1 (to extract student name)
        if ($hasAutoParts || $pageNumber === 1) {
            $mlkitHint = $request->input('mlkit_hint');

            try {
                $result = $this->extractor->extractAnswers($imageBytes, $pageSpec, $mlkitHint);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('YLE extract failed', [
                    'submission_id' => $submission->id,
                    'page_number' => $pageNumber,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($result) {
                $page->update(['ai_raw_response' => [
                    'raw' => json_encode($result),
                    'provider' => get_class($this->extractor),
                ]]);

                // Save student name from page 1
                if ($pageNumber === 1 && $result->studentName && ! $submission->ocr_raw_name) {
                    $submission->update(['ocr_raw_name' => $result->studentName]);
                    $candidates = $this->fuzzyMatch->findCandidates(
                        $result->studentName,
                        $submission->class_id
                    );
                }

                // Save and grade each answer — only for auto-gradable parts
                foreach ($result->answers as $answerItem) {
                    $question = YleQuestion::whereHas('part', function ($q) use ($exam, $answerItem) {
                        $q->where('yle_exam_id', $exam->id)
                            ->where('part_number', $answerItem->partNumber)
                            ->where('is_auto_gradable', true);
                    })->where('question_number', $answerItem->questionNumber)->first();

                    if (! $question) {
                        continue;
                    }

                    $part = $question->part;
                    $gradeResult = ($part->question_type === 'tick_cross' || $part->question_type === 'yes_no')
                        ? $this->grading->gradeTickCross($answerItem->value, $question)
                        : $this->grading->gradeAnswer($answerItem->value, $question);

                    YleAnswer::updateOrCreate(
                        [
                            'yle_submission_id' => $submission->id,
                            'yle_question_id' => $question->id,
                        ],
                        [
                            'student_answer' => $answerItem->value,
                            'is_correct' => $gradeResult['is_correct'],
                            'marks_awarded' => $gradeResult['marks_awarded'],
                            'graded_by' => 'auto',
                            'ai_confidence' => $answerItem->confidence,
                        ]
                    );

                    $returnedAnswers[] = [
                        'partNumber' => $answerItem->partNumber,
                        'questionNumber' => $answerItem->questionNumber,
                        'value' => $answerItem->value,
                        'isCorrect' => $gradeResult['is_correct'],
                        'confidence' => $answerItem->confidence,
                    ];
                }

                $this->recalculateTotals($submission);
            }
        }

        // Check if all pages uploaded → auto_graded (or needs_review if low confidence)
        $exam = $submission->fresh()->exam;
        $uploadedPages = YleSubmissionPage::where('yle_submission_id', $submission->id)->count();
        if ($uploadedPages >= $exam->total_pages) {
            $hasNeedsReview = YleAnswer::where('yle_submission_id', $submission->id)
                ->where('graded_by', 'auto')
                ->where('ai_confidence', '<', 0.6)
                ->exists();
            $submission->update(['status' => $hasNeedsReview ? 'needs_review' : 'auto_graded']);
        } elseif ($hasAutoParts && ! empty($returnedAnswers)) {
            $submission->update(['status' => 'grading']);
        }

        return [
            'answers' => $returnedAnswers,
            'candidates' => $candidates,
        ];
    }

    private function recalculateTotals(YleSubmission $submission): void
    {
        $autoScore = YleAnswer::where('yle_submission_id', $submission->id)
            ->where('graded_by', 'auto')
            ->sum('marks_awarded');

        // Use stored manual_score (per-question guessing is not done for manual parts)
        $manualScore = $submission->manual_score ?? YleAnswer::where('yle_submission_id', $submission->id)
            ->where('graded_by', 'manual')
            ->sum('marks_awarded');

        $totalScore = $autoScore + $manualScore;

        $hasNeedsReview = YleAnswer::where('yle_submission_id', $submission->id)
            ->where('graded_by', 'auto')
            ->where('ai_confidence', '<', 0.6)
            ->exists();

        $status = $submission->status;
        if ($hasNeedsReview && in_array($status, ['auto_graded', 'completed'])) {
            $status = 'needs_review';
        }

        $submission->update([
            'auto_score' => $autoScore,
            'manual_score' => $manualScore,
            'total_score' => $totalScore,
            'status' => $status,
        ]);
    }

    private function buildBreakdown(YleSubmission $submission): array
    {
        $exam = $submission->exam;
        $breakdown = [];

        foreach ($exam->parts as $part) {
            $partCorrect = 0;
            $partTotal = $part->max_marks;
            $questions = [];

            foreach ($part->questions as $question) {
                $answer = $submission->answers->firstWhere('yle_question_id', $question->id);
                $questions[] = [
                    'questionNumber' => $question->question_number,
                    'studentAnswer' => $answer?->student_answer,
                    'correctAnswer' => $question->correct_answer,
                    'isCorrect' => $answer?->is_correct,
                    'marksAwarded' => $answer?->marks_awarded ?? 0,
                    'gradedBy' => $answer?->graded_by ?? 'pending',
                    'aiConfidence' => $answer?->ai_confidence,
                ];

                if ($answer?->is_correct === true) {
                    $partCorrect++;
                }
            }

            $breakdown[] = [
                'partId' => $part->id,
                'partNumber' => $part->part_number,
                'pageNumber' => $part->page_number,
                'title' => $part->title,
                'isAutoGradable' => $part->is_auto_gradable,
                'score' => $partCorrect,
                'maxMarks' => $partTotal,
                'questions' => $questions,
            ];
        }

        return $breakdown;
    }

    private function formatSubmission(YleSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'yleExamId' => $submission->yle_exam_id,
            'classId' => $submission->class_id,
            'studentId' => $submission->student_id,
            'studentName' => $submission->student?->full_name,
            'examDate' => $submission->exam_date?->format('Y-m-d'),
            'ocrRawName' => $submission->ocr_raw_name,
            'autoScore' => $submission->auto_score,
            'manualScore' => $submission->manual_score,
            'totalScore' => $submission->total_score,
            'maxScore' => $submission->max_score,
            'status' => $submission->status,
            'createdAt' => $submission->created_at,
            'pagesCount' => $submission->pages->count(),
        ];
    }

    private function formatAnswer(YleAnswer $answer): array
    {
        return [
            'id' => $answer->id,
            'questionId' => $answer->yle_question_id,
            'studentAnswer' => $answer->student_answer,
            'isCorrect' => $answer->is_correct,
            'marksAwarded' => $answer->marks_awarded,
            'gradedBy' => $answer->graded_by,
            'aiConfidence' => $answer->ai_confidence,
        ];
    }
}
