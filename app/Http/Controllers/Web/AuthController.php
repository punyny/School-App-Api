<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\LoginLinkToken;
use App\Models\User;
use App\Notifications\WebMagicLoginLinkNotification;
use App\Support\WebPanelSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\Factory as ViewFactory;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AuthController extends Controller
{
    public function showLogin(Request $request): View
    {
        return view('web.auth.login', [
            'showMagicLinkPreview' => $this->shouldExposeMagicLinkPreview($request),
            'showLogMailerNotice' => $this->shouldShowLogMailerNotice($request),
        ]);
    }

    public function login(Request $request, WebPanelSession $session): RedirectResponse
    {
        $authMethod = $this->resolveAuthMethod($request);
        $throttleKey = $this->throttleKey($request);
        $maxAttempts = (int) config('security.login.max_attempts', 5);
        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'login' => ["Too many login attempts. Try again in {$retryAfter} seconds."],
            ])->status(429);
        }

        $decaySeconds = (int) config('security.login.decay_seconds', 60);
        $payload = $request->validate([
            'auth_method' => ['nullable', 'in:magic_link,password'],
            'login' => ['required', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'max:255', 'required_if:auth_method,password'],
        ]);

        RateLimiter::hit($throttleKey, $decaySeconds);

        $email = Str::lower(trim((string) $payload['login']));
        $user = User::query()
            ->where('email', $email)
            ->first();

        if ($authMethod === 'password') {
            $storedPassword = $user?->password ?? $user?->password_hash;
            $isValidPassword = $user
                && $user->active !== false
                && $user->is_active !== false
                && is_string($storedPassword)
                && $storedPassword !== ''
                && Hash::check((string) ($payload['password'] ?? ''), $storedPassword);

            if (! $isValidPassword) {
                return back()
                    ->withInput($request->except('password'))
                    ->withErrors([
                        'login' => [__('auth.failed')],
                    ]);
            }

            $session->login($request, $user);
            RateLimiter::clear($throttleKey);

            return redirect()->away($this->resolveIntendedPath($request));
        }

        if ($user && $user->active !== false && $user->is_active !== false) {
            try {
                $magicLoginUrl = $this->sendMagicLoginLink($user, $request);
            } catch (TransportExceptionInterface $exception) {
                Log::warning('Unable to send magic login email.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'message' => $exception->getMessage(),
                ]);

                return back()->withErrors([
                    'login' => ['មិនអាចផ្ញើ email ចូលប្រើបានទេ។ សូមពិនិត្យ Gmail SMTP / App Password រួចសាកម្តងទៀត។'],
                ]);
            }

            $response = back()->with('status', 'If that account exists, we sent a sign-in link to its email address.');

            if ($this->shouldExposeMagicLinkPreview($request)) {
                $response->with('debug_magic_login_url', $magicLoginUrl)
                    ->with('debug_magic_login_path', $this->relativePathFromUrl($magicLoginUrl))
                    ->with('debug_magic_login_email', $user->email);
            }

            return $response;
        }

        return back()->with('status', 'If that account exists, we sent a sign-in link to its email address.');
    }

    public function magicLogin(Request $request, int $id, string $token): View|RedirectResponse
    {
        $validated = $this->validateMagicLinkOrRedirect($id, $token);
        if ($validated instanceof RedirectResponse) {
            return $validated;
        }

        return view('web.auth.magic-login', [
            'user' => $validated['user'],
            'consumeUrl' => $request->getRequestUri(),
            'expiresAt' => $this->expiresAtFromRequest($request),
        ]);
    }

    public function consumeMagicLogin(Request $request, int $id, string $token, WebPanelSession $session): RedirectResponse
    {
        $validated = $this->validateMagicLinkOrRedirect($id, $token);
        if ($validated instanceof RedirectResponse) {
            return $validated;
        }

        $user = $validated['user'];

        $wasConsumed = DB::transaction(function () use ($user, $token): bool {
            $loginLinkToken = $this->activeLoginLinkTokenQuery($user->id, $token, 'web')
                ->lockForUpdate()
                ->first();

            if (! $loginLinkToken instanceof LoginLinkToken) {
                return false;
            }

            $loginLinkToken->forceFill([
                'consumed_at' => now(),
            ])->save();

            return true;
        });

        if (! $wasConsumed) {
            return redirect()->away(route('login', [], false))
                ->withErrors(['login' => ['This sign-in link is invalid or has already been used.']]);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $session->login($request, $user);

        return redirect()->away($this->resolveIntendedPath($request));
    }

    public function mobileLogin(Request $request, int $id, string $token): View|ViewFactory
    {
        $user = User::query()->findOrFail($id);

        abort_unless(in_array((string) $user->role, ['teacher', 'student', 'parent'], true), 404);

        $mobileLoginUrl = $this->mobileMagicLinkUrl(
            $user->id,
            $token,
            $request->query('device_name')
        );

        $webFallbackUrl = (string) ($request->query('web_fallback') ?? route('login'));

        return view('web.auth.mobile-login', [
            'mobileLoginUrl' => $mobileLoginUrl,
            'webFallbackUrl' => $webFallbackUrl,
            'expiresInMinutes' => 15,
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = $request->user();
        $tokenId = $request->session()->pull('web_api_token_id');

        if ($user && $tokenId) {
            $user->tokens()->whereKey($tokenId)->delete();
        }

        $request->session()->forget('web_api_token');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->away(route('login', [], false));
    }

    private function resolveIntendedPath(Request $request): string
    {
        $intended = $request->session()->pull('url.intended');

        if (is_string($intended) && $intended !== '') {
            if (str_starts_with($intended, '/')) {
                return $intended;
            }

            $path = parse_url($intended, PHP_URL_PATH);
            if (is_string($path) && str_starts_with($path, '/')) {
                $query = parse_url($intended, PHP_URL_QUERY);

                return is_string($query) && $query !== '' ? $path.'?'.$query : $path;
            }
        }

        return route('dashboard', [], false);
    }

    private function throttleKey(Request $request): string
    {
        $login = (string) ($request->input('login') ?? '');
        $authMethod = $this->resolveAuthMethod($request);

        return Str::lower($login).'|'.$request->ip().'|web|'.$authMethod;
    }

    private function resolveAuthMethod(Request $request): string
    {
        $authMethod = (string) ($request->input('auth_method') ?? 'magic_link');

        return in_array($authMethod, ['magic_link', 'password'], true)
            ? $authMethod
            : 'magic_link';
    }

    private function sendMagicLoginLink(User $user, Request $request): string
    {
        $token = Str::random(64);
        $expiresInMinutes = 15;
        $expiresAt = now()->addMinutes($expiresInMinutes);
        $rootUrl = $this->resolveSignedWebRootUrl($request);

        $this->deleteStaleLoginLinkTokens($user->id, 'web');

        LoginLinkToken::query()->create([
            'user_id' => $user->id,
            'channel' => 'web',
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
        ]);

        $url = $this->signedWebUrl(
            'login.magic',
            $expiresAt,
            [
                'id' => $user->id,
                'token' => $token,
            ],
            $rootUrl,
        );

        $user->notify(new WebMagicLoginLinkNotification($url, $expiresInMinutes));

        return $url;
    }

    /**
     * @return array{user: User, loginLinkToken: LoginLinkToken}|RedirectResponse
     */
    private function validateMagicLinkOrRedirect(int $id, string $token): array|RedirectResponse
    {
        $user = User::query()->findOrFail($id);
        $loginLinkToken = $this->activeLoginLinkTokenQuery($user->id, $token, 'web')->first();

        if (! $loginLinkToken instanceof LoginLinkToken) {
            return redirect()->away(route('login', [], false))
                ->withErrors(['login' => ['This sign-in link is invalid or has already been used.']]);
        }

        if ($user->active === false || $user->is_active === false) {
            return redirect()->away(route('login', [], false))
                ->withErrors(['login' => ['This account is inactive.']]);
        }

        return [
            'user' => $user,
            'loginLinkToken' => $loginLinkToken,
        ];
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

    private function expiresAtFromRequest(Request $request): ?string
    {
        $expires = $request->query('expires');
        if (! is_scalar($expires) || ! is_numeric((string) $expires)) {
            return null;
        }

        return now()->setTimestamp((int) $expires)->toDateTimeString();
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

    private function mobileMagicLinkUrl(int $userId, string $token, mixed $deviceName): string
    {
        $base = (string) config('app.mobile_magic_link_base', 'schoolmobile://login');
        $separator = str_contains($base, '?') ? '&' : '?';

        $query = [
            'id' => $userId,
            'token' => $token,
        ];

        if (is_string($deviceName) && $deviceName !== '') {
            $query['device_name'] = $deviceName;
        }

        return $base.$separator.http_build_query($query);
    }

    private function shouldExposeMagicLinkPreview(?Request $request = null): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        if (! app()->environment('local')) {
            return false;
        }

        if (! (bool) env('APP_SHOW_MAGIC_LINK_PREVIEW', false)) {
            return false;
        }

        return $this->isAllowedPreviewHost($request);
    }

    private function shouldShowLogMailerNotice(?Request $request = null): bool
    {
        return app()->environment('local')
            && (string) config('mail.default') === 'log'
            && ! $this->shouldExposeMagicLinkPreview($request);
    }

    private function isAllowedPreviewHost(?Request $request = null): bool
    {
        $host = Str::lower(trim((string) ($request?->getHost() ?? '')));
        if ($host === '') {
            return false;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1', 'school-api.test'], true)) {
            return true;
        }

        if ($this->isPrivateIpv4Host($host)) {
            return true;
        }

        return str_ends_with($host, '.test');
    }

    private function isPrivateIpv4Host(string $host): bool
    {
        return preg_match('/^(10\.\d{1,3}\.\d{1,3}\.\d{1,3}|192\.168\.\d{1,3}\.\d{1,3}|172\.(1[6-9]|2\d|3[0-1])\.\d{1,3}\.\d{1,3})$/', $host) === 1;
    }

    private function relativePathFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);

        if (! is_string($path) || $path === '') {
            return $url;
        }

        return is_string($query) && $query !== ''
            ? $path.'?'.$query
            : $path;
    }
}
