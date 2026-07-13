<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function classStats(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        if (! $request->user()->isAdmin() && ! $request->user()->classes()->where('class_id', $schoolClass->id)->exists()) {
            return response()->json(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền xem lớp này.'], 403);
        }

        $totalStudents = Student::where('class_id', $schoolClass->id)->count();

        $examIds = $schoolClass->exams()->pluck('id');

        $gradesQuery = DB::table('grades')->whereIn('exam_id', $examIds);
        $totalGrades = (clone $gradesQuery)->count();
        $avgScore = (clone $gradesQuery)->avg('score');
        $highestScore = (clone $gradesQuery)->max('score');
        $lowestScore = (clone $gradesQuery)->min('score');
        $totalExams = $examIds->count();

        return response()->json([
            'class' => [
                'id' => $schoolClass->id,
                'code' => $schoolClass->code,
                'name' => $schoolClass->name,
                'level' => $schoolClass->level,
            ],
            'stats' => [
                'total_students' => $totalStudents,
                'total_exams' => $totalExams,
                'total_grades' => $totalGrades,
                'average_score' => $avgScore ? round((float) $avgScore, 2) : null,
                'highest_score' => $highestScore ? round((float) $highestScore, 2) : null,
                'lowest_score' => $lowestScore ? round((float) $lowestScore, 2) : null,
            ],
        ]);
    }

    public function studentStats(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        if (! $request->user()->isAdmin() && ! $request->user()->classes()->where('class_id', $schoolClass->id)->exists()) {
            return response()->json(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền xem lớp này.'], 403);
        }

        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = $request->integer('per_page', 20);

        $examIds = $schoolClass->exams()->pluck('id');

        $students = Student::where('class_id', $schoolClass->id)
            ->with(['grades' => fn ($q) => $q->whereIn('exam_id', $examIds)])
            ->orderBy('full_name')
            ->paginate($perPage);

        return response()->json([
            'students' => collect($students->items())->map(fn ($s) => [
                'id' => $s->id,
                'full_name' => $s->full_name,
                'total_exams' => $s->grades->count(),
                'average_score' => $s->grades->isNotEmpty() ? round((float) $s->grades->avg('score'), 2) : null,
                'best_score' => $s->grades->isNotEmpty() ? round((float) $s->grades->max('score'), 2) : null,
                'worst_score' => $s->grades->isNotEmpty() ? round((float) $s->grades->min('score'), 2) : null,
            ]),
            'meta' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
            ],
        ]);
    }
}
