<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MobileMagicLoginLinkNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $bridgeLoginUrl,
        private readonly string $mobileLoginUrl,
        private readonly string $webFallbackUrl,
        private readonly int $expiresInMinutes = 15,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function bridgeLoginUrl(): string
    {
        return $this->bridgeLoginUrl;
    }

    public function mobileLoginUrl(): string
    {
        return $this->mobileLoginUrl;
    }

    public function webFallbackUrl(): string
    {
        return $this->webFallbackUrl;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Mobile sign-in link for School App')
            ->greeting('Hello '.$this->resolveName($notifiable).',')
            ->line('Tap the button below on your phone to open a secure sign-in page for the School mobile app.')
            ->line('This link can be used only once and expires soon.')
            ->action('Open School App', $this->bridgeLoginUrl)
            ->line('If the app does not open automatically, the sign-in page will give you another button to continue.')
            ->line('If you are on another device, you can still use the web login link below:')
            ->line($this->webFallbackUrl)
            ->line('This link expires in '.$this->expiresInMinutes.' minutes.')
            ->line('If you did not request this email, you can ignore it.');
    }

    private function resolveName(object $notifiable): string
    {
        $name = trim((string) ($notifiable->name ?? ''));

        return $name !== '' ? $name : 'there';
    }
}
