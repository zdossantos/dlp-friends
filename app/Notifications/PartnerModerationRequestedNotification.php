<?php

namespace App\Notifications;

use App\Contracts\WebPushNotification;
use App\Enums\RoleName;
use App\Enums\WebPushPreference;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Support\WebPushTarget;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class PartnerModerationRequestedNotification extends Notification implements WebPushNotification
{
    /** @param array<string, string> $parameters */
    public function __construct(
        public string $translationKey,
        public array $parameters,
        public string $targetType,
        public int $targetId,
    ) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database', 'broadcast', WebPushChannel::class];
    }

    /** @return array<string, mixed> */
    public function toArray(User $notifiable): array
    {
        return [
            'category' => 'administration',
            'translation_key' => $this->translationKey,
            'parameters' => $this->parameters,
            'target_type' => $this->targetType,
            'target_id' => $this->targetId,
        ];
    }

    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(['id' => $this->id, ...$this->toArray($notifiable)]);
    }

    public function webPushPreference(): WebPushPreference
    {
        return WebPushPreference::Administration;
    }

    public function webPushTarget(User $notifiable): WebPushTarget
    {
        return new WebPushTarget('/admin/partner-announcements');
    }

    public function webPushAccessAllowed(User $notifiable): bool
    {
        return $notifiable->hasRole(RoleName::Admin);
    }
}
