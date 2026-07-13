<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamController extends Controller
{
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
            ],
        ]);
    }

    public function storeToday(Request $request): JsonResponse
    {
        $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'total_questions' => 'required|integer|min:1|max:500',
            'max_score' => 'nullable|integer|min:1|max:100',
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
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'date' => $exam->date->format('Y-m-d'),
                'totalQuestions' => $exam->total_questions,
                'maxScore' => $exam->max_score,
            ],
        ], 201);
    }
}
