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
    private const LOGIN_METHOD_PASSWORD = 'password';
    private const LOGIN_METHOD_ACCESS_TOKEN = 'access_token';
    private const LOGIN_METHOD_MAGIC_LINK = 'magic_link';
    private const MOBILE_MAGIC_LINK_EXPIRY_MINUTES = 15;
    private const MOBILE_SUPPORTED_ROLES = ['super-admin', 'admin', 'teacher', 'student', 'parent'];

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $authMethod = $this->resolveAuthMethodFromCredentials($credentials);
        if ($authMethod === self::LOGIN_METHOD_ACCESS_TOKEN) {
            return $this->loginWithMagicAccessToken(
                $request,
                (int) $credentials['id'],
                (string) $credentials['token'],
                $credentials['device_name'] ?? null
            );
        }

        $isLocalEnvironment = app()->environment('local');
        $throttleKey = $this->throttleKey($request, $authMethod);
        $maxAttempts = (int) config('security.login.max_attempts', 5);
        if (! $isLocalEnvironment && RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            return $this->tooManyAttemptsResponse($throttleKey);
        }

        $decaySeconds = (int) config('security.login.decay_seconds', 60);
        $login = trim((string) ($credentials['login'] ?? $credentials['email'] ?? ''));
        $normalizedLogin = Str::lower($login);
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$normalizedLogin])
            ->orWhereRaw('LOWER(username) = ?', [$normalizedLogin])
            ->orWhereRaw('LOWER(user_code) = ?', [$normalizedLogin])
            ->orWhere('phone', $login)
            ->first();

        $storedPassword = (string) ($user?->password ?? '');
        if ($storedPassword === '') {
            $storedPassword = (string) ($user?->password_hash ?? '');
        }

        $providedPassword = (string) ($credentials['password'] ?? '');
        $isValidPassword = $storedPassword !== '' && Hash::check($providedPassword, $storedPassword);

        // Local dev fallback: allow password123 for quick mobile testing.
        if (! $isValidPassword && $isLocalEnvironment && $providedPassword !== '') {
            $isValidPassword = hash_equals('password123', $providedPassword);
        }

        if (! $user || ! $isValidPassword) {
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

        if (! $this->supportsMobileAccessRole($user)) {
            return $this->mobileRoleNotSupportedResponse();
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        RateLimiter::clear($throttleKey);

        return $this->issueMobileAccessTokenResponse(
            $request,
            $user,
            $credentials['device_name'] ?? null,
            'Login successful.',
            self::LOGIN_METHOD_PASSWORD,
        );
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
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($user && ! $this->supportsMobileAccessRole($user)) {
            return $this->mobileRoleNotSupportedResponse();
        }

        if ($user && ($user->active === false || $user->is_active === false)) {
            return response()->json([
                'message' => 'This account is inactive.',
            ], 403);
        }

        if ($user && $this->canUseMobileMagicLink($user)) {
            try {
                $this->sendMobileMagicLoginLink($user, $request, $payload['device_name'] ?? null);
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

        return $this->loginWithMagicAccessToken(
            $request,
            (int) $payload['id'],
            (string) $payload['token'],
            $payload['device_name'] ?? null
        );
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

    private function loginWithMagicAccessToken(
        Request $request,
        int $userId,
        string $token,
        ?string $deviceName = null
    ): JsonResponse {
        $user = User::query()->findOrFail($userId);
        if (! $this->supportsMobileAccessRole($user)) {
            return response()->json([
                'message' => 'This account is not supported by mobile login.',
            ], 403);
        }

        $isConsumed = DB::transaction(function () use ($user, $token): bool {
            $loginLinkToken = $this->activeLoginLinkTokenQuery(
                $user->id,
                $token,
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

        return $this->issueMobileAccessTokenResponse(
            $request,
            $user,
            $deviceName,
            'Magic link verified successfully.',
            self::LOGIN_METHOD_ACCESS_TOKEN,
        );
    }

    private function issueMobileAccessTokenResponse(
        Request $request,
        User $user,
        ?string $deviceName = null,
        string $message = 'Login successful.',
        string $authMethod = self::LOGIN_METHOD_PASSWORD,
    ): JsonResponse {
        $user->forceFill([
            'last_login' => now(),
        ])->save();

        $tokenName = $deviceName ?? ($request->userAgent() ?: 'api-token');
        $token = $user->createToken((string) $tokenName)->plainTextToken;

        return response()->json([
            'message' => $message,
            'token' => $token,
            'token_type' => 'Bearer',
            'auth_method' => $authMethod,
            'user' => $this->mobileUserPayload($user),
        ]);
    }

    private function throttleKey(Request $request, string $authMethod): string
    {
        if ($authMethod === self::LOGIN_METHOD_ACCESS_TOKEN) {
            $id = (string) ($request->input('id') ?? '');

            return $id.'|'.$request->ip().'|api-access-token';
        }

        $login = (string) ($request->input('login') ?? $request->input('email') ?? '');

        return Str::lower($login).'|'.$request->ip().'|api-password';
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function resolveAuthMethodFromCredentials(array $credentials): string
    {
        $authMethod = (string) ($credentials['auth_method'] ?? '');

        if (in_array($authMethod, [self::LOGIN_METHOD_PASSWORD, self::LOGIN_METHOD_ACCESS_TOKEN], true)) {
            return $authMethod;
        }

        if ($authMethod === self::LOGIN_METHOD_MAGIC_LINK) {
            return self::LOGIN_METHOD_ACCESS_TOKEN;
        }

        return filled($credentials['token'] ?? null) || filled($credentials['id'] ?? null)
            ? self::LOGIN_METHOD_ACCESS_TOKEN
            : self::LOGIN_METHOD_PASSWORD;
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
        return $this->supportsMobileAccessRole($user)
            && $user->active !== false
            && $user->is_active !== false
            && filled($user->email);
    }

    private function supportsMobileAccessRole(User $user): bool
    {
        return in_array((string) $user->role, self::MOBILE_SUPPORTED_ROLES, true);
    }

    private function mobileRoleNotSupportedResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'This account must sign in on the web portal. Mobile login is available only for super-admin, admin, teacher, student, and parent accounts.',
        ], 403);
    }

    private function sendMobileMagicLoginLink(User $user, Request $request, ?string $deviceName = null): void
    {
        $mobileToken = Str::random(64);
        $webToken = Str::random(64);
        $expiresInMinutes = self::MOBILE_MAGIC_LINK_EXPIRY_MINUTES;
        $expiresAt = now()->addMinutes($expiresInMinutes);
        $rootUrl = $this->resolveSignedWebRootUrl($request);

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
            $rootUrl,
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
            $rootUrl,
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
    private function signedWebUrl(
        string $routeName,
        \DateTimeInterface $expiration,
        array $parameters = [],
        ?string $rootUrl = null
    ): string
    {
        $rootUrl = rtrim((string) ($rootUrl ?? config('app.url')), '/');

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

    private function resolveSignedWebRootUrl(Request $request): string
    {
        $requestRoot = rtrim((string) $request->getSchemeAndHttpHost(), '/');
        $requestHost = Str::lower((string) $request->getHost());

        if ($requestRoot !== '' && ! in_array($requestHost, ['localhost', '127.0.0.1', '::1', '10.0.2.2'], true)) {
            return $requestRoot;
        }

        return rtrim((string) config('app.url'), '/');
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
