<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\WebMagicLoginLinkNotification;
use App\Support\WebPanelSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
    public function showLogin(): View
    {
        return view('web.auth.login', [
            'demoAccounts' => $this->demoAccounts(),
            'showMagicLinkPreview' => $this->shouldExposeMagicLinkPreview(),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
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
            'login' => ['required', 'string', 'max:100'],
        ]);

        RateLimiter::hit($throttleKey, $decaySeconds);

        $login = trim((string) $payload['login']);
        $user = User::query()
            ->where('email', $login)
            ->orWhere('username', $login)
            ->first();

        if ($user && $user->active !== false && $user->is_active !== false) {
            try {
                $magicLoginUrl = $this->sendMagicLoginLink($user);
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

            if ($this->shouldExposeMagicLinkPreview()) {
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
        $user = $this->validateMagicLinkOrRedirect($id, $token);
        if ($user instanceof RedirectResponse) {
            return $user;
        }

        return view('web.auth.magic-login', [
            'user' => $user,
            'consumeUrl' => $request->getRequestUri(),
            'expiresAt' => $this->expiresAtFromRequest($request),
        ]);
    }

    public function consumeMagicLogin(Request $request, int $id, string $token, WebPanelSession $session): RedirectResponse
    {
        $user = $this->validateMagicLinkOrRedirect($id, $token);
        if ($user instanceof RedirectResponse) {
            return $user;
        }

        $cacheKey = $this->magicLinkCacheKey($user->id, $token);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        Cache::forget($cacheKey);
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

        return Str::lower($login).'|'.$request->ip().'|web';
    }

    private function sendMagicLoginLink(User $user): string
    {
        $token = Str::random(64);
        $expiresInMinutes = 15;
        $cacheKey = $this->magicLinkCacheKey($user->id, $token);
        Cache::put($cacheKey, $token, now()->addMinutes($expiresInMinutes));

        $url = $this->signedWebUrl(
            'login.magic',
            now()->addMinutes($expiresInMinutes),
            [
                'id' => $user->id,
                'token' => $token,
            ],
        );

        $user->notify(new WebMagicLoginLinkNotification($url, $expiresInMinutes));

        return $url;
    }

    private function magicLinkCacheKey(int $userId, string $token): string
    {
        return 'web-magic-login:'.$userId.':'.hash('sha256', $token);
    }

    private function validateMagicLinkOrRedirect(int $id, string $token): User|RedirectResponse
    {
        $user = User::query()->findOrFail($id);
        $cacheKey = $this->magicLinkCacheKey($user->id, $token);
        $storedToken = Cache::get($cacheKey);

        if (! is_string($storedToken) || ! hash_equals($storedToken, $token)) {
            return redirect()->away(route('login', [], false))
                ->withErrors(['login' => ['This sign-in link is invalid or has already been used.']]);
        }

        if ($user->active === false || $user->is_active === false) {
            return redirect()->away(route('login', [], false))
                ->withErrors(['login' => ['This account is inactive.']]);
        }

        return $user;
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

    /**
     * @return array<int, array{role:string,email:string}>
     */
    private function demoAccounts(): array
    {
        if (! $this->shouldExposeMagicLinkPreview()) {
            return [];
        }

        return [
            ['role' => 'Super Admin', 'email' => 'superadmin@example.com'],
            ['role' => 'Admin', 'email' => 'admin@example.com'],
            ['role' => 'Teacher', 'email' => 'teacher@example.com'],
            ['role' => 'Student', 'email' => 'student@example.com'],
            ['role' => 'Parent', 'email' => 'parent@example.com'],
        ];
    }

    private function shouldExposeMagicLinkPreview(): bool
    {
        return app()->environment(['local', 'testing']) || (bool) config('app.debug');
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
