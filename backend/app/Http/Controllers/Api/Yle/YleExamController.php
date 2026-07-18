<?php

namespace App\Http\Controllers\Api\Yle;

use App\Http\Controllers\Controller;
use App\Models\Yle\YleExam;
use App\Models\Yle\YlePart;
use App\Models\Yle\YleQuestion;
use App\Support\YleTemplates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class YleExamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'level' => 'nullable|in:starters,movers,flyers',
            'skill' => 'nullable|in:listening,reading_writing,speaking',
        ]);

        $query = YleExam::with('parts.questions');

        if ($request->has('level')) {
            $query->where('level', $request->input('level'));
        }

        if ($request->has('skill')) {
            $query->where('skill', $request->input('skill'));
        }

        $exams = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'exams' => $exams->map(fn ($exam) => $this->formatExam($exam)),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $exam = YleExam::with('parts.questions')->findOrFail($id);

        return response()->json([
            'exam' => $this->formatExam($exam),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'level' => 'required|in:starters,movers,flyers',
            'skill' => 'required|in:listening,reading_writing,speaking',
            'name' => 'nullable|string|max:255',
        ]);

        $template = YleTemplates::get($request->input('level'), $request->input('skill'));

        if (! $template) {
            return response()->json([
                'error' => 'TEMPLATE_NOT_FOUND',
                'message' => 'Không tìm thấy mẫu đề cho cấp độ/kỹ năng này.',
            ], 422);
        }

        $exam = YleExam::create([
            'level' => $request->input('level'),
            'skill' => $request->input('skill'),
            'name' => $request->input('name', $template['name']),
            'total_marks' => $template['total_marks'],
            'total_pages' => $template['total_pages'],
            'created_by' => $request->user()->id,
        ]);

        foreach ($template['parts'] as $partData) {
            $questions = $partData['questions'];
            unset($partData['questions']);

            $part = YlePart::create(array_merge($partData, [
                'yle_exam_id' => $exam->id,
            ]));

            foreach ($questions as $qData) {
                YleQuestion::create(array_merge($qData, [
                    'yle_part_id' => $part->id,
                ]));
            }
        }

        $exam->load('parts.questions');

        return response()->json([
            'exam' => $this->formatExam($exam),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $exam = YleExam::findOrFail($id);

        if ($request->user()->cannot('update', $exam)) {
            return response()->json(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền sửa bài thi này.'], 403);
        }

        $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        if ($request->has('name')) {
            $exam->update(['name' => $request->input('name')]);
        }

        $exam->load('parts.questions');

        return response()->json([
            'exam' => $this->formatExam($exam),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $exam = YleExam::findOrFail($id);
        $exam->delete();

        return response()->json(null, 204);
    }

    public function updateQuestion(Request $request, int $id): JsonResponse
    {
        $question = YleQuestion::with('part.exam')->findOrFail($id);

        if ($request->user()->cannot('update', $question)) {
            return response()->json(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền sửa câu hỏi này.'], 403);
        }

        $request->validate([
            'correct_answer' => 'nullable|string|max:255',
            'accepted_variants' => 'nullable|array',
            'accepted_variants.*' => 'string|max:255',
            'prompt' => 'nullable|string|max:255',
        ]);

        $question->update($request->only(['correct_answer', 'accepted_variants', 'prompt']));

        return response()->json([
            'question' => [
                'id' => $question->id,
                'partId' => $question->yle_part_id,
                'questionNumber' => $question->question_number,
                'prompt' => $question->prompt,
                'correctAnswer' => $question->correct_answer,
                'acceptedVariants' => $question->accepted_variants ?? [],
                'points' => $question->points,
            ],
        ]);
    }

    private function formatExam(YleExam $exam): array
    {
        return [
            'id' => $exam->id,
            'level' => $exam->level,
            'skill' => $exam->skill,
            'name' => $exam->name,
            'totalMarks' => $exam->total_marks,
            'totalPages' => $exam->total_pages,
            'createdBy' => $exam->creator?->name,
            'createdAt' => $exam->created_at,
            'parts' => $exam->parts->map(fn ($part) => [
                'id' => $part->id,
                'partNumber' => $part->part_number,
                'title' => $part->title,
                'questionType' => $part->question_type,
                'isAutoGradable' => $part->is_auto_gradable,
                'maxMarks' => $part->max_marks,
                'pageNumber' => $part->page_number,
                'questions' => $part->questions->map(fn ($q) => [
                    'id' => $q->id,
                    'questionNumber' => $q->question_number,
                    'prompt' => $q->prompt,
                    'correctAnswer' => $q->correct_answer,
                    'acceptedVariants' => $q->accepted_variants ?? [],
                    'points' => $q->points,
                ]),
            ]),
        ];
    }
}
