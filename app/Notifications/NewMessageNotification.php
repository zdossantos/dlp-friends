<?php

namespace App\Notifications;

use App\Contracts\WebPushNotification;
use App\Enums\NotificationCategory;
use App\Enums\WebPushPreference;
use App\Models\Message;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Support\WebPushTarget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class NewMessageNotification extends Notification implements ShouldQueue, WebPushNotification
{
    use Queueable;

    public function __construct(public Message $message)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database', 'broadcast', WebPushChannel::class];
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

    public function webPushPreference(): WebPushPreference
    {
        return WebPushPreference::Messages;
    }

    public function webPushTarget(User $notifiable): WebPushTarget
    {
        return new WebPushTarget(route('conversations.show', $this->message->conversation_id, absolute: false));
    }

    public function webPushAccessAllowed(User $notifiable): bool
    {
        return $this->message->conversation()->forMember($notifiable)->withVisibleParticipant($notifiable)->exists();
    }
}
