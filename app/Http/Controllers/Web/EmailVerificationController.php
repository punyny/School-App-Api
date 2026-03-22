<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\WebPanelSession;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user?->hasVerifiedEmail()) {
            return redirect()->away(route('dashboard', [], false));
        }

        return view('web.auth.verify-email', [
            'user' => $user,
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->away(route('login', [], false));
        }

        if (! $user->hasVerifiedEmail()) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (TransportExceptionInterface $exception) {
                Log::warning('Unable to resend verification email.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'message' => $exception->getMessage(),
                ]);

                return back()->withErrors([
                    'login' => ['មិនអាចផ្ញើ email verify បានទេ។ សូមពិនិត្យ Gmail SMTP / App Password រួចសាកម្តងទៀត។'],
                ]);
            }
        }

        return back()->with('status', 'A new verification link has been sent to your email address.');
    }

    public function verify(Request $request, int $id, string $hash, WebPanelSession $session): RedirectResponse
    {
        $user = User::query()->findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        if ($user->active === false || $user->is_active === false) {
            return redirect()->away(route('login', [], false))
                ->withErrors(['login' => ['This account is inactive.']]);
        }

        $session->login($request, $user);

        return redirect()->away(route('dashboard', [], false))
            ->with('success', 'Email verified successfully. You are now signed in.');
    }
}
