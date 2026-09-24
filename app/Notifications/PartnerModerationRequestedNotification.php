<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class PartnerModerationRequestedNotification extends Notification
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
        return ['database', 'broadcast'];
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
}
