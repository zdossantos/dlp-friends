<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Message;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Message $message)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array{category: string, translation_key: string, parameters: array{sender: string|null}, target_type: string, target_id: int} */
    public function toArray(User $notifiable): array
    {
        $this->message->loadMissing(['author.profile', 'conversation']);

        return [
            'category' => NotificationCategory::Conversations->value,
            'translation_key' => 'notifications.items.new_message',
            'parameters' => ['sender' => $this->message->author->profile?->display_name],
            'target_type' => 'conversation',
            'target_id' => $this->message->conversation_id,
        ];
    }

    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            ...$this->toArray($notifiable),
        ]);
    }
}
