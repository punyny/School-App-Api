<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RealtimeNotificationBroadcasted implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public bool $afterCommit = true;

    /**
     * @param  array<int, int>  $recipientIds
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public array $recipientIds,
        public string $type,
        public string $title,
        public string $content,
        public array $meta = []
    ) {
        $this->recipientIds = array_values(array_unique(array_map('intval', $recipientIds)));
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [];

        foreach ($this->recipientIds as $recipientId) {
            if ($recipientId <= 0) {
                continue;
            }

            $channels[] = new PrivateChannel('users.'.$recipientId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'realtime.notification';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'content' => $this->content,
            'meta' => $this->meta,
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
