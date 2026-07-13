<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            Log::warning('Login failed', ['email' => $request->email]);

            return response()->json([
                'error' => 'UNAUTHORIZED',
                'message' => 'Email hoặc mật khẩu không đúng.',
            ], 401);
        }

        $user = auth()->user();

        Log::info('Login successful', ['user_id' => $user->id, 'email' => $user->email]);

        return $this->respondWithToken($token, $user);
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
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole('teacher');

        if ($request->class_code) {
            $class = SchoolClass::where('code', $request->class_code)->first();
            if ($class) {
                $user->classes()->syncWithoutDetaching([$class->id]);
            }
        }

        $token = JWTAuth::fromUser($user);

        Log::info('User registered', ['user_id' => $user->id, 'email' => $user->email]);

        return $this->respondWithToken($token, $user, 201);
    }

    public function me(): JsonResponse
    {
        $user = auth()->user()->load('classes');
        $user->load('roles');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'classes' => $user->classes,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());

            Log::info('User logged out', ['user_id' => auth()->id()]);

            return response()->json(['message' => 'Đã đăng xuất.']);
        } catch (JWTException $e) {
            return response()->json(['message' => 'Đã đăng xuất.'], 200);
        }
    }

    public function refresh(): JsonResponse
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());
            $user = auth()->user();

            return $this->respondWithToken($token, $user);
        } catch (JWTException $e) {
            return response()->json([
                'error' => 'TOKEN_INVALID',
                'message' => 'Token không hợp lệ hoặc đã hết hạn.',
            ], 401);
        }
    }

    protected function respondWithToken(string $token, ?User $user = null, int $status = 200): JsonResponse
    {
        $ttl = config('jwt.ttl', 60);

        $data = [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $ttl * 60,
        ];

        if ($user) {
            $user->load('classes');
            $data['user'] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ];
        }

        return response()->json($data, $status);
    }
}
