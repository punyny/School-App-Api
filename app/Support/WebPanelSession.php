<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        $token = $user->createToken('web-panel');
        $request->session()->put('web_api_token', $token->plainTextToken);
        $request->session()->put('web_api_token_id', $token->accessToken->id);
    }
}
