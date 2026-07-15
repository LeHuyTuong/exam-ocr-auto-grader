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
            'class_id' => 'required|exists:school_classes,id',
            'mlkit_hint' => 'nullable|string|max:500',
        ]);

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
                'message' => 'Chưa có bài thi hôm nay cho lớp này. Hãy tạo exam trước.',
            ], 404);
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

        $score = round(
            ($result->totalCorrect / $exam->total_questions) * $exam->max_score,
            2
        );

        $candidates = $this->fuzzyMatch->findCandidates($result->studentName, $classId);

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
            'class_id' => 'required|exists:school_classes,id',
            'mode' => 'required|in:name,scores',
            'mlkit_hint' => 'nullable|string|max:500',
        ]);

        $classId = $request->integer('class_id');
        $mode = $request->input('mode');

        if (! $request->user()->isAdmin() && ! $request->user()->classes()->where('class_id', $classId)->exists()) {
            return response()->json(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền truy cập lớp này.'], 403);
        }

        $exam = Exam::where('class_id', $classId)
            ->where('date', today())
            ->first();

        if (! $exam) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Chưa có bài thi hôm nay cho lớp này. Hãy tạo exam trước.',
            ], 404);
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
            $candidates = $this->fuzzyMatch->findCandidates($result->studentName, $classId);

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
}
