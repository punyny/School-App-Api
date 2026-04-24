<?php

namespace App\Jobs;

use App\Services\TelegramBotClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTelegramMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>|null  $replyMarkup
     */
    public function __construct(
        public string $chatId,
        public string $text,
        public array $meta = [],
        public ?array $replyMarkup = null,
    ) {}

    public function handle(TelegramBotClient $telegram): void
    {
        $telegram->sendMessage($this->chatId, $this->text, $this->meta, $this->replyMarkup);
    }
}
