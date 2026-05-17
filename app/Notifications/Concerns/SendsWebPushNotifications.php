<?php

namespace App\Notifications\Concerns;

use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

trait SendsWebPushNotifications
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [
            'database',
            WebPushChannel::class,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return [
            'database' => 'sync',
            WebPushChannel::class => config('queue.default'),
        ];
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->webPushTitle())
            ->body($this->webPushBody())
            ->icon(asset('images/logo.svg'))
            ->data([
                'url' => $this->webPushUrl($notifiable),
            ]);
    }

    abstract protected function webPushTitle(): string;

    abstract protected function webPushBody(): string;

    protected function webPushUrl(object $notifiable): string
    {
        if (filled($notifiable->role ?? null)) {
            return url($notifiable->role->getRedirectPath());
        }

        return url('/');
    }
}
