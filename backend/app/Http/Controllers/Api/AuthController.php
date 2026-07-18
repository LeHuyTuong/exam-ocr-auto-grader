<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::guard('web')->validate($request->only('email', 'password'))) {
            Log::warning('Login failed', [
                'email' => $request->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'error' => 'UNAUTHORIZED',
                'message' => 'Email hoặc mật khẩu không đúng.',
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('login')->plainTextToken;

        Log::info('Login successful', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return $this->respondWithToken($token, $user);
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/|regex:/[@$!%*?&]/',
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

        $token = $user->createToken('registration')->plainTextToken;

        Log::info('User registered', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return $this->respondWithToken($token, $user, 201);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('classes', 'roles');

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
        $token = $request->bearerToken();
        if ($token && $request->user()) {
            $tokenId = (int) explode('|', $token)[0];
            $request->user()->tokens()->where('id', $tokenId)->delete();
        }

        Log::info('User logged out', ['user_id' => $request->user()?->id]);

        return response()->json(['message' => 'Đã đăng xuất.']);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'error' => 'TOKEN_INVALID',
                'message' => 'Token không hợp lệ hoặc đã hết hạn.',
            ], 401);
        }

        $tokenId = null;
        $token = $request->bearerToken();
        if ($token) {
            $tokenId = (int) explode('|', $token)[0];
        }

        $newToken = $user->createToken('refresh')->plainTextToken;

        if ($tokenId) {
            $user->tokens()->where('id', $tokenId)->delete();
        }

        return $this->respondWithToken($newToken, $user);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => $status === Password::RESET_LINK_SENT
                ? 'Nếu email tồn tại trong hệ thống, bạn sẽ nhận được link đặt lại mật khẩu.'
                : 'Nếu email tồn tại trong hệ thống, bạn sẽ nhận được link đặt lại mật khẩu.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/|regex:/[@$!%*?&]/|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'error' => 'RESET_FAILED',
                'message' => 'Token không hợp lệ hoặc đã hết hạn.',
            ], 422);
        }

        Log::info('Password reset', ['email' => $request->email, 'ip' => $request->ip()]);

        return response()->json(['message' => 'Đặt lại mật khẩu thành công.']);
    }

    protected function respondWithToken(string $token, ?User $user = null, int $status = 200): JsonResponse
    {
        $expirationMinutes = config('sanctum.expiration', 43200); // default 30 days in minutes

        $data = [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $expirationMinutes * 60,
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
