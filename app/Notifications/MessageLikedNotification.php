<?php

namespace App\Notifications;

use App\Contracts\PersonalizedWebPushNotification;
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

final class MessageLikedNotification extends Notification implements PersonalizedWebPushNotification, ShouldQueue, WebPushNotification
{
    use Queueable;

    public function __construct(public Message $message, public User $reactor)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database', 'broadcast', WebPushChannel::class];
    }

    /** @return array{category: string, translation_key: string, parameters: array{member: string|null}, target_type: string, target_id: int} */
    public function toArray(User $notifiable): array
    {
        $this->reactor->loadMissing('profile');

        return [
            'category' => NotificationCategory::Conversations->value,
            'translation_key' => 'notifications.items.message_liked',
            'parameters' => ['member' => $this->reactor->profile?->display_name],
            'target_type' => 'conversation',
            'target_id' => $this->message->conversation_id,
        ];
    }

    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(['id' => $this->id, ...$this->toArray($notifiable)]);
    }

    public function webPushPreference(): WebPushPreference
    {
        return WebPushPreference::Messages;
    }

    /** @return array{title: string, body: string} */
    public function webPushCopy(User $notifiable, string $locale): array
    {
        return [
            'title' => __('notifications.push.reactions.title', locale: $locale),
            'body' => __('notifications.push.reactions.body', locale: $locale),
        ];
    }

    public function webPushTarget(User $notifiable): WebPushTarget
    {
        return new WebPushTarget(route('conversations.show', $this->message->conversation_id, absolute: false));
    }

    public function webPushAccessAllowed(User $notifiable): bool
    {
        return $this->message->conversation()->forMember($notifiable)->withUnblockedParticipant($notifiable)->exists();
    }
}
