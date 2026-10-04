<?php

namespace App\Notifications;

use App\Contracts\WebPushNotification;
use App\Enums\EventNotificationType;
use App\Enums\NotificationCategory;
use App\Enums\UserStatus;
use App\Enums\WebPushPreference;
use App\Models\Event;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Support\WebPushTarget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class EventLifecycleNotification extends Notification implements ShouldQueue, WebPushNotification
{
    use Queueable;

    public function __construct(public Event $event, public EventNotificationType $type)
    {
        $this->afterCommit();
    }

    public function shouldSend(User $notifiable, string $channel): bool
    {
        return $notifiable->fresh()?->status === UserStatus::Active;
    }

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database', 'broadcast', WebPushChannel::class];
    }

    /** @return array{category: string, translation_key: string, parameters: array{event: string}, target_type: string, target_id: int} */
    public function toArray(User $notifiable): array
    {
        return [
            'category' => NotificationCategory::Events->value,
            'translation_key' => 'notifications.items.event_'.$this->type->value,
            'parameters' => ['event' => $this->event->title],
            'target_type' => 'event',
            'target_id' => $this->event->id,
        ];
    }

    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(['id' => $this->id, ...$this->toArray($notifiable)]);
    }

    public function webPushPreference(): WebPushPreference
    {
        return WebPushPreference::Events;
    }

    public function webPushTarget(User $notifiable): WebPushTarget
    {
        return new WebPushTarget(route('events.show', $this->event, absolute: false));
    }

    public function webPushAccessAllowed(User $notifiable): bool
    {
        return $notifiable->can('view', $this->event);
    }
}
