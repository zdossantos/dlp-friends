<?php

namespace App\Notifications\Channels;

use App\Contracts\WebPushNotification;
use App\Enums\UserStatus;
use App\Jobs\SendWebPushNotification;
use App\Models\User;
use Illuminate\Notifications\Notification;

final class WebPushChannel
{
    public function send(User $notifiable, Notification $notification): void
    {
        if (! $notification instanceof WebPushNotification
            || $notifiable->status !== UserStatus::Active
            || $notifiable->deletion_requested_at !== null
            || ! $notification->webPushAccessAllowed($notifiable)
            || ! $this->configured()) {
            return;
        }

        $enabled = $notifiable->notificationPreferences()
            ->where('category', $notification->webPushPreference())
            ->value('enabled') ?? true;

        if (! $enabled) {
            return;
        }

        $notifiable->webPushSubscriptions()->whereNull('revoked_at')->pluck('id')
            ->each(fn (int $subscriptionId) => SendWebPushNotification::dispatch($subscriptionId, $notification));
    }

    private function configured(): bool
    {
        return filled(config('services.web_push.subject'))
            && filled(config('services.web_push.public_key'))
            && filled(config('services.web_push.private_key'));
    }
}
