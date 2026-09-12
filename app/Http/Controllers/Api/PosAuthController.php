<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Cấp và thu hồi token Sanctum ràng buộc với một thiết bị Tauri. */
final class PosAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_id' => ['required', 'uuid'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);
        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Thông tin đăng nhập không chính xác.']);
        }

        if (! $user->store?->is_active) {
            throw ValidationException::withMessages(['email' => 'Tài khoản chưa thuộc chi nhánh đang hoạt động.']);
        }

        $tokenName = 'tauri:'.$validated['device_id'];
        $user->tokens()->where('name', $tokenName)->delete();
        $expiresAt = now()->addMinutes((int) config('sanctum.expiration', 43200));
        $token = $user->createToken(
            $tokenName,
            ['pos:use', 'device:'.$validated['device_id']],
            $expiresAt,
        );

        return response()->json([
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $token->plainTextToken,
                'expires_at' => $expiresAt->toIso8601String(),
                'device' => [
                    'id' => $validated['device_id'],
                    'name' => $validated['device_name'],
                ],
                'user' => [
                    'id' => (int) $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                    'store_id' => (int) $user->store_id,
                ],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Đã đăng xuất thiết bị.']);
    }
}
