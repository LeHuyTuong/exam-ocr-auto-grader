<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            Log::warning('Login failed', ['email' => $request->email]);

            return response()->json([
                'error' => 'UNAUTHORIZED',
                'message' => 'Email hoặc mật khẩu không đúng.',
            ], 401);
        }

        $token = $user->createToken('mobile-app')->plainTextToken;

        Log::info('Login successful', ['user_id' => $user->id, 'email' => $user->email]);

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'class_code' => 'nullable|string|max:20|exists:school_classes,code',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => 'teacher',
        ]);

        if ($request->class_code) {
            $class = SchoolClass::where('code', $request->class_code)->first();
            if ($class) {
                $user->classes()->syncWithoutDetaching([$class->id]);
            }
        }

        $token = $user->createToken('mobile-app')->plainTextToken;

        Log::info('User registered', ['user_id' => $user->id, 'email' => $user->email]);

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        Log::info('User logged out', ['user_id' => $request->user()->id]);

        return response()->json(['message' => 'Đã đăng xuất.']);
    }
}
