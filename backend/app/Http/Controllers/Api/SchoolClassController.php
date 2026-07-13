<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;

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
}
