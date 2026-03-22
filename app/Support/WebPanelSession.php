<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class WebPanelSession
{
    public function login(Request $request, User $user): void
    {
        $existingUser = $request->user();
        $existingTokenId = $request->session()->pull('web_api_token_id');
        if ($existingUser && $existingTokenId) {
            $existingUser->tokens()->whereKey($existingTokenId)->delete();
        }

        $request->session()->forget('web_api_token');

        Auth::login($user);
        $request->session()->regenerate();

        $expiresAt = now()->addMinutes((int) config('session.lifetime', 120));
        $token = $user->createToken('web-panel', ['*'], $expiresAt);
        $request->session()->put('web_api_token', Crypt::encryptString($token->plainTextToken));
        $request->session()->put('web_api_token_id', $token->accessToken->id);
    }
}
