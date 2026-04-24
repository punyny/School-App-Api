<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TelegramAccountLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramLinkController extends Controller
{
    public function issueLinkCode(
        Request $request,
        TelegramAccountLinkService $telegramAccountLinkService,
    ): JsonResponse {
        $user = $request->user();

        if (! (bool) config('services.telegram.enabled', false) || trim((string) config('services.telegram.bot_token', '')) === '') {
            return response()->json([
                'message' => 'Telegram integration is not enabled.',
            ], 422);
        }

        $payload = $telegramAccountLinkService->createLinkCode($user);
        $code = (string) $payload['code'];
        $ttlMinutes = $telegramAccountLinkService->linkCodeTtlMinutes();

        return response()->json([
            'message' => 'Telegram link code generated.',
            'data' => [
                'code' => $code,
                'command' => '/link '.$code,
                'expires_at' => $payload['expires_at']->toIso8601String(),
                'expires_in_minutes' => $ttlMinutes,
                'instructions' => [
                    'Open your Telegram bot chat.',
                    'Send: /link '.$code,
                ],
            ],
        ], 201);
    }
}
