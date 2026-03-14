<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChangePasswordRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Models\User;
use App\Support\ProfileImageStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $throttleKey = $this->throttleKey($request);
        $maxAttempts = (int) config('security.login.max_attempts', 5);
        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            return $this->tooManyAttemptsResponse($throttleKey);
        }

        $decaySeconds = (int) config('security.login.decay_seconds', 60);
        $credentials = $request->validated();
        $login = trim((string) ($credentials['login'] ?? $credentials['email'] ?? ''));
        $user = User::query()
            ->where('email', $login)
            ->orWhere('username', $login)
            ->first();

        $storedPassword = $user?->password ?? $user?->password_hash;

        if (! $user || ! $storedPassword || ! Hash::check($credentials['password'], $storedPassword)) {
            RateLimiter::hit($throttleKey, $decaySeconds);

            return response()->json([
                'message' => 'Invalid credentials.',
            ], 422);
        }

        if ($user->active === false || $user->is_active === false) {
            RateLimiter::hit($throttleKey, $decaySeconds);

            return response()->json([
                'message' => 'This account is inactive.',
            ], 403);
        }

        RateLimiter::clear($throttleKey);

        $user->forceFill([
            'last_login' => now(),
        ])->save();

        $tokenName = $credentials['device_name'] ?? ($request->userAgent() ?: 'api-token');
        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logout successful.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing($this->profileRelations((string) $user->role));

        return response()->json([
            'user' => $user,
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $payload = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'image' => ProfileImageStorage::uploadValidationRules(),
            'remove_image' => ['nullable', 'boolean'],
        ]);

        $updates = [];
        foreach (['name', 'phone', 'address', 'bio'] as $field) {
            if (array_key_exists($field, $payload)) {
                $updates[$field] = $payload[$field];
            }
        }

        $removeImage = ($payload['remove_image'] ?? false) === true;

        if ($removeImage) {
            $updates['image_url'] = null;
        } elseif ($request->hasFile('image')) {
        } elseif (array_key_exists('image_url', $payload)) {
            $updates['image_url'] = $payload['image_url'];
        }

        if ($updates !== []) {
            $user->fill($updates)->save();
        }

        if ($removeImage) {
            ProfileImageStorage::clearPrimaryForModel($user);
        } elseif ($request->hasFile('image')) {
            $imageUrl = ProfileImageStorage::storeForModel(
                $request->file('image'),
                $user,
                $user,
                'profiles/self'
            );
            $user->forceFill(['image_url' => $imageUrl])->save();
        }

        $user->loadMissing($this->profileRelations((string) $user->role));

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user,
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $payload = $request->validated();
        $storedPassword = $user->password ?? $user->password_hash;

        if (! $storedPassword || ! Hash::check($payload['current_password'], $storedPassword)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $newPasswordHash = Hash::make($payload['new_password']);
        $user->password = $newPasswordHash;
        $user->password_hash = $newPasswordHash;
        $user->save();

        $user->tokens()->delete();
        $newToken = $user->createToken('password-changed')->plainTextToken;

        return response()->json([
            'message' => 'Password changed successfully.',
            'token' => $newToken,
            'token_type' => 'Bearer',
        ]);
    }

    private function throttleKey(Request $request): string
    {
        $login = (string) ($request->input('login') ?? $request->input('email') ?? '');

        return Str::lower($login).'|'.$request->ip().'|api';
    }

    private function tooManyAttemptsResponse(string $throttleKey): JsonResponse
    {
        $retryAfter = RateLimiter::availableIn($throttleKey);

        return response()->json([
            'message' => "Too many login attempts. Please try again in {$retryAfter} seconds.",
            'retry_after' => $retryAfter,
        ], 429);
    }

    /**
     * @return array<int, string>
     */
    private function profileRelations(string $role): array
    {
        $relations = ['studentProfile.class', 'studentProfile.parents'];

        if (in_array($role, ['parent', 'guardian'], true)) {
            $relations[] = 'children.user';
            $relations[] = 'children.class';
        }

        return $relations;
    }
}
