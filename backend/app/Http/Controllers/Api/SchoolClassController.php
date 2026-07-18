<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolClassController extends Controller
{
    public function mine(): JsonResponse
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            $classes = SchoolClass::all();
        } else {
            $classes = $user->classes;
        }

        return response()->json([
            'classes' => $classes->map(fn ($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'level' => $c->level,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:50|unique:school_classes,code',
            'name' => 'required|string|max:255',
            'level' => 'nullable|in:primary,secondary',
        ]);

        $class = SchoolClass::create([
            'code' => $request->input('code'),
            'name' => $request->input('name'),
            'level' => $request->input('level', 'primary'),
        ]);

        // Giáo viên tạo lớp thì tự động là GV phụ trách lớp đó — nếu không,
        // lớp vừa tạo sẽ không hiện trong "/classes/mine" của chính họ.
        $user = $request->user();
        if (! $user->isAdmin()) {
            $class->teachers()->attach($user->id);
        }

        return response()->json([
            'class' => [
                'id' => $class->id,
                'code' => $class->code,
                'name' => $class->name,
                'level' => $class->level,
            ],
        ], 201);
    }
}
