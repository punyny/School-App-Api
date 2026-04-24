<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramBotClient
{
    public function isEnabled(): bool
    {
        $enabled = (bool) config('services.telegram.enabled', false);
        $token = trim((string) config('services.telegram.bot_token', ''));

        return $enabled && $token !== '';
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>|null  $replyMarkup
     */
    public function sendMessage(string $chatId, string $text, array $meta = [], ?array $replyMarkup = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $normalizedChatId = trim($chatId);
        $normalizedText = $this->normalizeText($text);

        if ($normalizedChatId === '' || $normalizedText === '') {
            return false;
        }

        $payload = [
            'chat_id' => $normalizedChatId,
            'text' => $normalizedText,
            'disable_web_page_preview' => (bool) config('services.telegram.disable_web_page_preview', true),
        ];

        $parseMode = trim((string) config('services.telegram.parse_mode', ''));
        if ($parseMode !== '') {
            $payload['parse_mode'] = $parseMode;
        }

        if (is_array($replyMarkup) && $replyMarkup !== []) {
            $encodedReplyMarkup = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_string($encodedReplyMarkup) && $encodedReplyMarkup !== '') {
                $payload['reply_markup'] = $encodedReplyMarkup;
            }
        }

        return $this->postForm(
            endpoint: $this->sendMessageEndpoint(),
            payload: $payload,
            method: 'sendMessage',
            meta: $meta,
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function answerCallbackQuery(
        string $callbackQueryId,
        string $text = '',
        bool $showAlert = false,
        array $meta = [],
    ): bool {
        if (! $this->isEnabled()) {
            return false;
        }

        $normalizedCallbackId = trim($callbackQueryId);
        if ($normalizedCallbackId === '') {
            return false;
        }

        $payload = [
            'callback_query_id' => $normalizedCallbackId,
        ];

        $normalizedText = $this->normalizeText($text);
        if ($normalizedText !== '') {
            $payload['text'] = $normalizedText;
        }

        if ($showAlert) {
            $payload['show_alert'] = true;
        }

        return $this->postForm(
            endpoint: $this->answerCallbackQueryEndpoint(),
            payload: $payload,
            method: 'answerCallbackQuery',
            meta: $meta,
        );
    }

    private function sendMessageEndpoint(): string
    {
        $baseUrl = rtrim((string) config('services.telegram.base_url', 'https://api.telegram.org'), '/');
        $token = trim((string) config('services.telegram.bot_token', ''));

        return $baseUrl.'/bot'.$token.'/sendMessage';
    }

    private function answerCallbackQueryEndpoint(): string
    {
        $baseUrl = rtrim((string) config('services.telegram.base_url', 'https://api.telegram.org'), '/');
        $token = trim((string) config('services.telegram.bot_token', ''));

        return $baseUrl.'/bot'.$token.'/answerCallbackQuery';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $meta
     */
    private function postForm(string $endpoint, array $payload, string $method, array $meta = []): bool
    {
        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout((int) config('services.telegram.timeout_seconds', 10))
                ->post($endpoint, $payload);
        } catch (Throwable $exception) {
            Log::warning('Telegram API request failed.', [
                'method' => $method,
                'error' => $exception->getMessage(),
                'meta' => $meta,
            ]);

            return false;
        }

        if (! $response->successful() || (bool) $response->json('ok') !== true) {
            Log::warning('Telegram API returned an error response.', [
                'method' => $method,
                'status' => $response->status(),
                'response' => $response->json(),
                'meta' => $meta,
            ]);

            return false;
        }

        return true;
    }

    private function normalizeText(string $text): string
    {
        return trim(mb_strimwidth($text, 0, 3900, '...'));
    }
}
