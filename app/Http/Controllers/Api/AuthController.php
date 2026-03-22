<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChangePasswordRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Models\LoginLinkToken;
use App\Models\User;
use App\Notifications\MobileMagicLoginLinkNotification;
use App\Support\ProfileImageStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AuthController extends Controller
{
    private const MOBILE_MAGIC_LINK_EXPIRY_MINUTES = 15;

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

        if (! $user->hasVerifiedEmail()) {
            RateLimiter::hit($throttleKey, $decaySeconds);

            return response()->json([
                'message' => 'Please verify your email address before logging in.',
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
            'user' => $this->mobileUserPayload($user),
        ]);
    }

    public function requestMagicLink(Request $request): JsonResponse
    {
        $throttleKey = $this->magicLinkThrottleKey($request);
        $maxAttempts = (int) config('security.login.max_attempts', 5);
        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            return $this->tooManyAttemptsResponse($throttleKey);
        }

        $decaySeconds = (int) config('security.login.decay_seconds', 60);
        $payload = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        RateLimiter::hit($throttleKey, $decaySeconds);

        $email = Str::lower(trim((string) $payload['email']));
        $user = User::query()->where('email', $email)->first();

        if ($user && $this->canUseMobileMagicLink($user)) {
            try {
                $this->sendMobileMagicLoginLink($user, $payload['device_name'] ?? null);
            } catch (TransportExceptionInterface $exception) {
                Log::warning('Unable to send mobile magic login email.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'message' => $exception->getMessage(),
                ]);

                return response()->json([
                    'message' => 'Unable to send the sign-in email right now.',
                ], 500);
            }
        }

        return response()->json([
            'message' => 'If that account exists, we sent a sign-in link to its email address.',
        ]);
    }

    public function verifyMagicLink(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'id' => ['required', 'integer', 'exists:users,id'],
            'token' => ['required', 'string', 'min:32', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::query()->findOrFail((int) $payload['id']);
        if (! $this->canUseMobileMagicLink($user)) {
            return response()->json([
                'message' => 'This account role is not supported by the mobile app.',
            ], 403);
        }

        $isConsumed = DB::transaction(function () use ($user, $payload): bool {
            $loginLinkToken = $this->activeLoginLinkTokenQuery(
                $user->id,
                (string) $payload['token'],
                'mobile'
            )->lockForUpdate()->first();

            if (! $loginLinkToken instanceof LoginLinkToken) {
                return false;
            }

            $loginLinkToken->forceFill([
                'consumed_at' => now(),
            ])->save();

            return true;
        });

        if (! $isConsumed) {
            return response()->json([
                'message' => 'This sign-in link is invalid or has already been used.',
            ], 422);
        }

        if ($user->active === false || $user->is_active === false) {
            return response()->json([
                'message' => 'This account is inactive.',
            ], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $user->forceFill([
            'last_login' => now(),
        ])->save();

        $tokenName = $payload['device_name'] ?? ($request->userAgent() ?: 'mobile-magic-link');
        $token = $user->createToken((string) $tokenName)->plainTextToken;
        $user->loadMissing($this->profileRelations((string) $user->role));

        return response()->json([
            'message' => 'Magic link verified successfully.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->mobileUserPayload($user),
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
            'user' => $this->mobileUserPayload($user),
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
            'user' => $this->mobileUserPayload($user),
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

    private function magicLinkThrottleKey(Request $request): string
    {
        $email = (string) ($request->input('email') ?? '');

        return Str::lower($email).'|'.$request->ip().'|api-magic';
    }

    private function tooManyAttemptsResponse(string $throttleKey): JsonResponse
    {
        $retryAfter = RateLimiter::availableIn($throttleKey);

        return response()->json([
            'message' => "Too many login attempts. Please try again in {$retryAfter} seconds.",
            'retry_after' => $retryAfter,
        ], 429);
    }

    private function canUseMobileMagicLink(User $user): bool
    {
        return in_array((string) $user->role, ['teacher', 'student', 'parent'], true)
            && $user->active !== false
            && $user->is_active !== false
            && filled($user->email);
    }

    private function sendMobileMagicLoginLink(User $user, ?string $deviceName = null): void
    {
        $mobileToken = Str::random(64);
        $webToken = Str::random(64);
        $expiresInMinutes = self::MOBILE_MAGIC_LINK_EXPIRY_MINUTES;
        $expiresAt = now()->addMinutes($expiresInMinutes);

        $this->deleteStaleLoginLinkTokens($user->id, 'mobile');
        $this->deleteStaleLoginLinkTokens($user->id, 'web');

        LoginLinkToken::query()->create([
            'user_id' => $user->id,
            'channel' => 'mobile',
            'token_hash' => hash('sha256', $mobileToken),
            'expires_at' => $expiresAt,
        ]);

        LoginLinkToken::query()->create([
            'user_id' => $user->id,
            'channel' => 'web',
            'token_hash' => hash('sha256', $webToken),
            'expires_at' => $expiresAt,
        ]);

        $mobileUrl = $this->mobileMagicLinkUrl($user, $mobileToken, $deviceName);
        $fallbackWebUrl = $this->signedWebUrl(
            'login.magic',
            $expiresAt,
            [
                'id' => $user->id,
                'token' => $webToken,
            ],
        );
        $bridgeUrl = $this->signedWebUrl(
            'login.mobile',
            $expiresAt,
            [
                'id' => $user->id,
                'token' => $mobileToken,
                'device_name' => $deviceName,
                'web_fallback' => $fallbackWebUrl,
            ],
        );

        $user->notify(new MobileMagicLoginLinkNotification(
            bridgeLoginUrl: $bridgeUrl,
            mobileLoginUrl: $mobileUrl,
            webFallbackUrl: $fallbackWebUrl,
            expiresInMinutes: $expiresInMinutes,
        ));
    }

    private function mobileMagicLinkUrl(User $user, string $token, ?string $deviceName = null): string
    {
        $base = (string) config('app.mobile_magic_link_base', 'schoolmobile://login');
        $separator = str_contains($base, '?') ? '&' : '?';

        $query = [
            'id' => (int) $user->id,
            'token' => $token,
        ];

        if ($deviceName) {
            $query['device_name'] = $deviceName;
        }

        return $base.$separator.http_build_query($query);
    }

    private function activeLoginLinkTokenQuery(int $userId, string $token, string $channel)
    {
        return LoginLinkToken::query()
            ->where('user_id', $userId)
            ->where('channel', $channel)
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id');
    }

    private function deleteStaleLoginLinkTokens(int $userId, string $channel): void
    {
        LoginLinkToken::query()
            ->where('user_id', $userId)
            ->where('channel', $channel)
            ->where(function ($query): void {
                $query->where('expires_at', '<=', now())
                    ->orWhereNotNull('consumed_at');
            })
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function signedWebUrl(string $routeName, \DateTimeInterface $expiration, array $parameters = []): string
    {
        $rootUrl = rtrim((string) config('app.url'), '/');

        $relativeUrl = URL::temporarySignedRoute(
            $routeName,
            $expiration,
            $parameters,
            false
        );

        if ($rootUrl === '') {
            return url($relativeUrl);
        }

        return $rootUrl.$relativeUrl;
    }

    /**
     * @return array<string, mixed>
     */
    private function mobileUserPayload(User $user): array
    {
        $user->loadMissing($this->profileRelations((string) $user->role));
        $payload = $user->toArray();
        $school = $user->school;
        $payload['school_name'] = $school?->name;
        $payload['school_logo_url'] = $this->extractSchoolLogoUrl(
            is_array($school?->config_details) ? $school->config_details : null
        );

        return $payload;
    }

    private function extractSchoolLogoUrl(?array $configDetails): ?string
    {
        if ($configDetails === null) {
            return null;
        }

        foreach (['school_logo_url', 'logo_url', 'logo', 'image_url', 'app_logo_url', 'brand_logo_url'] as $key) {
            $candidate = $this->normalizeLogoUrl($configDetails[$key] ?? null);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        $branding = $configDetails['branding'] ?? null;
        if (is_array($branding)) {
            foreach (['logo_url', 'logo', 'image_url'] as $key) {
                $candidate = $this->normalizeLogoUrl($branding[$key] ?? null);
                if ($candidate !== null) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function normalizeLogoUrl(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        if (Str::startsWith($raw, '/')) {
            return url($raw);
        }

        return $raw;
    }

    /**
     * @return array<int, string>
     */
    private function profileRelations(string $role): array
    {
        $relations = ['school', 'studentProfile.class', 'studentProfile.parents'];

        if (in_array($role, ['parent', 'guardian'], true)) {
            $relations[] = 'children.user';
            $relations[] = 'children.class';
        }

        return $relations;
    }
}
