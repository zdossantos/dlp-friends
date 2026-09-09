<?php

namespace App\Notifications;

use App\Enums\EventNotificationType;
use App\Enums\NotificationCategory;
use App\Models\Event;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class EventLifecycleNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Event $event, public EventNotificationType $type)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database', 'broadcast'];
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
}
