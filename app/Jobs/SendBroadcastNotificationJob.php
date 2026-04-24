<?php

namespace App\Jobs;

use App\Events\RealtimeNotificationBroadcasted;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendBroadcastNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, int>  $recipientIds
     */
    public function __construct(
        public array $recipientIds,
        public string $title,
        public string $content,
        public string $type = 'broadcast'
    ) {}

    public function handle(): void
    {
        $recipientIds = array_values(array_unique(array_filter(
            array_map('intval', $this->recipientIds),
            fn (int $id): bool => $id > 0
        )));

        if ($recipientIds === []) {
            return;
        }

        $now = now();
        $validIds = User::query()->whereIn('id', $recipientIds)->pluck('id')->all();
        if ($validIds === []) {
            return;
        }

        foreach ($validIds as $recipientId) {
            Notification::query()->create([
                'user_id' => $recipientId,
                'title' => $this->title,
                'content' => $this->content,
                'date' => $now,
                'read_status' => false,
            ]);
        }

        event(new RealtimeNotificationBroadcasted(
            recipientIds: $validIds,
            type: $this->type,
            title: $this->title,
            content: $this->content,
            meta: ['source' => 'queued-broadcast']
        ));

        $this->queueTelegramBroadcastDeliveries($validIds);
    }

    /**
     * @param  array<int, int>  $recipientIds
     */
    private function queueTelegramBroadcastDeliveries(array $recipientIds): void
    {
        if (! (bool) config('services.telegram.enabled', false)) {
            return;
        }

        $targets = User::query()
            ->whereIn('id', $recipientIds)
            ->whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->get(['id', 'telegram_chat_id']);

        if ($targets->isEmpty()) {
            return;
        }

        $telegramText = $this->buildTelegramBroadcastText();

        foreach ($targets as $target) {
            SendTelegramMessageJob::dispatch(
                chatId: (string) $target->telegram_chat_id,
                text: $telegramText,
                meta: [
                    'source' => 'broadcast-notification',
                    'type' => $this->type,
                    'recipient_user_id' => (int) $target->id,
                ]
            );
        }
    }

    private function buildTelegramBroadcastText(): string
    {
        $title = trim($this->title);
        $content = trim(mb_strimwidth($this->content, 0, 800, '...'));
        $type = trim($this->type);

        return implode("\n", [
            '[Sala Digital]',
            'Broadcast notification',
            'Type: '.($type !== '' ? $type : 'broadcast'),
            'Title: '.($title !== '' ? $title : 'Notice'),
            'Content: '.$content,
        ]);
    }
}
