<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('web.auth.login');
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
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $remember = (bool) ($credentials['remember'] ?? false);
        $login = trim((string) $credentials['login']);
        $user = User::query()
            ->where('email', $login)
            ->orWhere('username', $login)
            ->first();
        $storedPassword = $user?->password ?? $user?->password_hash;

        if (! $user || ! $storedPassword || ! Hash::check((string) $credentials['password'], $storedPassword)) {
            RateLimiter::hit($throttleKey, $decaySeconds);

            throw ValidationException::withMessages([
                'login' => ['Invalid credentials.'],
            ]);
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();

        $user = $request->user();

        if ($user?->active === false || $user?->is_active === false) {
            RateLimiter::hit($throttleKey, $decaySeconds);
            Auth::logout();

            throw ValidationException::withMessages([
                'login' => ['This account is inactive.'],
            ]);
        }

        RateLimiter::clear($throttleKey);

        if ($user) {
            $newToken = $user->createToken('web-panel');
            $request->session()->put('web_api_token', $newToken->plainTextToken);
            $request->session()->put('web_api_token_id', $newToken->accessToken->id);
        }

        return redirect()->away($this->resolveIntendedPath($request));
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
}
