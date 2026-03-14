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
    }
}
