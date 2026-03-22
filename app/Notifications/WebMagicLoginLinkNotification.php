<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WebMagicLoginLinkNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $loginUrl,
        private readonly int $expiresInMinutes = 15,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function loginUrl(): string
    {
        return $this->loginUrl;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('លីងសុវត្ថិភាពសម្រាប់ចូលប្រើ')
            ->greeting('សួស្តី '.$this->resolveName($notifiable).',')
            ->line('សូមចុចប៊ូតុងខាងក្រោម ដើម្បីចូលទៅកាន់ School Portal។')
            ->line('លីងនេះនឹងចូលប្រើឲ្យអ្នក ហើយបញ្ជាក់អ៊ីមែលរបស់អ្នកដោយស្វ័យប្រវត្តិ។')
            ->line('លីងនេះអាចប្រើបានតែ 1 ដងប៉ុណ្ណោះ។')
            ->action('ចូលប្រើ និងបញ្ជាក់អ៊ីមែល', $this->loginUrl)
            ->line('លីងនេះនឹងផុតកំណត់ក្នុងរយៈពេល '.$this->expiresInMinutes.' នាទី។')
            ->line('បើអ្នកមិនបានស្នើសុំ email នេះទេ សូមមិនអើពើបាន។');
    }

    private function resolveName(object $notifiable): string
    {
        $name = trim((string) ($notifiable->name ?? ''));

        return $name !== '' ? $name : 'there';
    }
}
