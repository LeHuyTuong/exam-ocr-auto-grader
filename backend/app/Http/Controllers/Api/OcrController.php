<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\CloudinaryService;
use App\Services\FuzzyMatchService;
use App\Services\Vision\GradedPaperExtractor;
use App\Services\Vision\VisionExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OcrController extends Controller
{
    public function __construct(
        private VisionExtractor $extractor,
        private GradedPaperExtractor $gradedExtractor,
        private CloudinaryService $cloudinary,
        private FuzzyMatchService $fuzzyMatch,
    ) {}

    public function extract(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|max:10240',
            'exam_id' => 'required|exists:exams,id',
            'mlkit_hint' => 'nullable|string|max:500',
        ]);

        $exam = Exam::with('class')->findOrFail($request->integer('exam_id'));
        $schoolClass = $exam->class;
        $classId = $schoolClass->id;

        if ($request->user()->cannot('view', $schoolClass)) {
            Log::warning('Access denied: ocr.extract', ['user_id' => $request->user()->id, 'class_id' => $classId, 'ip' => $request->ip()]);
            return response()->json(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền truy cập lớp này.'], 403);
        }

        if (! $exam->is_active) {
            return response()->json([
                'error' => 'EXAM_LOCKED',
                'message' => 'Đề thi này đã khoá, không thể chấm thêm.',
            ], 403);
        }

        $imageBytes = file_get_contents($request->file('image')->getRealPath());

        try {
            $hint = $request->input('mlkit_hint');
            $hint = $hint ? preg_replace('/[^\p{L}\p{N}\s]/u', '', $hint) : null;
            $result = $this->extractor->extract($imageBytes, $hint);
        } catch (\Exception $e) {
            Log::warning('OCR extraction failed', ['class_id' => $classId, 'error' => $e->getMessage()]);

            return response()->json([
                'error' => 'AI_ERROR',
                'message' => 'Không thể đọc ảnh. Vui lòng chụp lại ảnh rõ hơn.',
            ], 422);
        }

        try {
            $imageUrl = $this->cloudinary->upload($imageBytes);
        } catch (\Exception $e) {
            Log::warning('Cloudinary upload failed', ['class_id' => $classId, 'error' => $e->getMessage()]);
            $imageUrl = null;
        }

        $score = $exam->total_questions > 0
            ? round(($result->totalCorrect / $exam->total_questions) * $exam->max_score, 2)
            : 0.0;

        $candidates = $this->safeFindCandidates($result->studentName, $classId);

        return response()->json([
            'candidates' => $candidates,
            'totalCorrect' => $result->totalCorrect,
            'score' => $score,
            'ocrRawName' => $result->studentName,
            'aiConfidence' => $result->confidence,
            'imageUrl' => $imageUrl,
            'examId' => $exam->id,
        ]);
    }

    /**
     * Read an already-hand-graded Unit Test page, captured as two tight crops:
     * "name" (the pencil-written name line) and "scores" (the red-ink score
     * strip). Each call handles one crop; the mobile app calls this twice.
     */
    public function extractGraded(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|max:10240',
            'exam_id' => 'required|exists:exams,id',
            'mode' => 'required|in:name,scores',
            'mlkit_hint' => 'nullable|string|max:500',
        ]);

        $exam = Exam::with('class')->findOrFail($request->integer('exam_id'));
        $schoolClass = $exam->class;
        $classId = $schoolClass->id;
        $mode = $request->input('mode');

        if ($request->user()->cannot('view', $schoolClass)) {
            Log::warning('Access denied: ocr.extract', ['user_id' => $request->user()->id, 'class_id' => $classId, 'ip' => $request->ip()]);
            return response()->json(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền truy cập lớp này.'], 403);
        }

        if (! $exam->is_active) {
            return response()->json([
                'error' => 'EXAM_LOCKED',
                'message' => 'Đề thi này đã khoá, không thể chấm thêm.',
            ], 403);
        }

        $imageBytes = file_get_contents($request->file('image')->getRealPath());

        try {
            $hint = $request->input('mlkit_hint');
            $hint = $hint ? preg_replace('/[^\p{L}\p{N}\s]/u', '', $hint) : null;
            $result = $this->gradedExtractor->extract($imageBytes, $mode, $hint);
        } catch (\Exception $e) {
            Log::warning('Graded paper extraction failed', [
                'class_id' => $classId,
                'mode' => $mode,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'AI_ERROR',
                'message' => 'Không thể đọc ảnh. Vui lòng chụp lại ảnh rõ hơn.',
            ], 422);
        }

        try {
            $imageUrl = $this->cloudinary->upload($imageBytes);
        } catch (\Exception $e) {
            Log::warning('Cloudinary upload failed', ['class_id' => $classId, 'error' => $e->getMessage()]);
            $imageUrl = null;
        }

        if ($mode === 'name') {
            $candidates = $this->safeFindCandidates($result->studentName, $classId);

            return response()->json([
                'candidates' => $candidates,
                'ocrRawName' => $result->studentName,
                'aiConfidence' => $result->confidence,
                'imageUrl' => $imageUrl,
                'examId' => $exam->id,
            ]);
        }

        $subScores = $result->subScores ?? [];
        $sum = array_sum($subScores);
        $sumMismatch = $result->totalScore !== null && $sum !== $result->totalScore;

        return response()->json([
            'subScores' => $subScores,
            'totalScore' => $result->totalScore,
            'sumMismatch' => $sumMismatch,
            'aiConfidence' => $result->confidence,
            'imageUrl' => $imageUrl,
            'examId' => $exam->id,
        ]);
    }

    /**
     * Fuzzy name-matching is a best-effort convenience, not core to reading the
     * paper. Bad student data (a null normalized_name / alias) can make it throw
     * a \Throwable that isn't a \Exception, so guard it here and degrade to an
     * empty candidate list rather than crashing the whole OCR request with a 500.
     *
     * @return array<int, array{studentId:int, fullName:string, similarity:float}>
     */
    private function safeFindCandidates(?string $name, int $classId): array
    {
        if ($name === null || trim($name) === '') {
            return [];
        }

        try {
            return $this->fuzzyMatch->findCandidates($name, $classId);
        } catch (\Throwable $e) {
            Log::warning('Fuzzy name match failed', ['class_id' => $classId, 'error' => $e->getMessage()]);

            return [];
        }
    }
}
