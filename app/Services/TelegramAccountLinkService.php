<?php

namespace App\Services;

use App\Models\TelegramLinkToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TelegramAccountLinkService
{
    /**
     * @return array{code:string, expires_at:\Illuminate\Support\Carbon}
     */
    public function createLinkCode(User $user): array
    {
        $this->deleteStaleTokensForUser((int) $user->id);

        $expiresAt = now()->addMinutes($this->linkCodeTtlMinutes());
        $code = $this->generateLinkCode();
        $tokenHash = $this->linkCodeHash($code);

        DB::transaction(function () use ($user, $expiresAt, $tokenHash): void {
            TelegramLinkToken::query()
                ->where('user_id', (int) $user->id)
                ->whereNull('consumed_at')
                ->delete();

            TelegramLinkToken::query()->create([
                'user_id' => (int) $user->id,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
            ]);
        });

        return [
            'code' => $code,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * @return array{
     *   status:string,
     *   user?:User
     * }
     */
    public function consumeLinkCode(string $linkCode, string $chatId): array
    {
        $normalizedCode = $this->normalizeLinkCode($linkCode);
        $normalizedChatId = trim($chatId);

        if ($normalizedCode === '' || $normalizedChatId === '') {
            return ['status' => 'invalid_code'];
        }

        $tokenHash = $this->linkCodeHash($normalizedCode);
        $token = TelegramLinkToken::query()
            ->where('token_hash', $tokenHash)
            ->latest('id')
            ->first();

        if (! $token) {
            return ['status' => 'invalid_code'];
        }

        if ($token->consumed_at) {
            return ['status' => 'already_used'];
        }

        if ($token->expires_at <= now()) {
            return ['status' => 'expired'];
        }

        return DB::transaction(function () use ($token, $normalizedChatId): array {
            $managedToken = TelegramLinkToken::query()
                ->lockForUpdate()
                ->find((int) $token->id);

            if (! $managedToken) {
                return ['status' => 'invalid_code'];
            }

            if ($managedToken->consumed_at) {
                return ['status' => 'already_used'];
            }

            if ($managedToken->expires_at <= now()) {
                return ['status' => 'expired'];
            }

            $user = User::query()
                ->lockForUpdate()
                ->find((int) $managedToken->user_id);
            if (! $user) {
                return ['status' => 'invalid_code'];
            }

            $owner = User::query()
                ->where('telegram_chat_id', $normalizedChatId)
                ->where('id', '!=', (int) $user->id)
                ->lockForUpdate()
                ->first();
            if ($owner) {
                return ['status' => 'chat_in_use'];
            }

            $user->telegram_chat_id = $normalizedChatId;
            $user->save();

            $consumedAt = now();
            $managedToken->consumed_at = $consumedAt;
            $managedToken->consumed_chat_id = $normalizedChatId;
            $managedToken->save();

            TelegramLinkToken::query()
                ->where('user_id', (int) $user->id)
                ->where('id', '!=', (int) $managedToken->id)
                ->whereNull('consumed_at')
                ->update([
                    'consumed_at' => $consumedAt,
                    'consumed_chat_id' => $normalizedChatId,
                ]);

            return [
                'status' => 'linked',
                'user' => $user->fresh(),
            ];
        });
    }

    public function linkCodeTtlMinutes(): int
    {
        $minutes = (int) config('services.telegram.link_code_ttl_minutes', 15);

        return max(5, min($minutes, 1440));
    }

    private function deleteStaleTokensForUser(int $userId): void
    {
        TelegramLinkToken::query()
            ->where('user_id', $userId)
            ->where(function ($query): void {
                $query->where('expires_at', '<=', now())
                    ->orWhereNotNull('consumed_at');
            })
            ->delete();
    }

    private function generateLinkCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $length = 8;
        $maxIndex = strlen($alphabet) - 1;

        $code = '';
        for ($index = 0; $index < $length; $index++) {
            $code .= $alphabet[random_int(0, $maxIndex)];
        }

        return $code;
    }

    private function normalizeLinkCode(string $raw): string
    {
        $normalized = strtoupper(trim($raw));

        return preg_replace('/[^A-Z0-9]/', '', $normalized) ?? '';
    }

    private function linkCodeHash(string $code): string
    {
        return hash_hmac('sha256', $code, $this->hashingKey());
    }

    private function hashingKey(): string
    {
        $appKey = trim((string) config('app.key', ''));
        $botToken = trim((string) config('services.telegram.bot_token', ''));

        return hash('sha256', $appKey.'|'.$botToken.'|telegram-account-link-code');
    }
}
