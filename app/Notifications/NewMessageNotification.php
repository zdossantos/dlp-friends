<?php

namespace App\Notifications;

use App\Contracts\PersonalizedWebPushNotification;
use App\Contracts\WebPushNotification;
use App\Enums\NotificationCategory;
use App\Enums\UserStatus;
use App\Enums\WebPushPreference;
use App\Models\Message;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Support\WebPushTarget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

final class NewMessageNotification extends Notification implements PersonalizedWebPushNotification, ShouldQueue, WebPushNotification
{
    use Queueable;

    public function __construct(public Message $message)
    {
        $this->afterCommit();
    }

    public function shouldSend(User $notifiable, string $channel): bool
    {
        $fresh = $notifiable->fresh();

        return $fresh !== null && $fresh->status === UserStatus::Active && $this->webPushAccessAllowed($fresh);
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

    /** @return array{title: string, body: string} */
    public function webPushCopy(User $notifiable, string $locale): array
    {
        $this->message->loadMissing('author.profile');

        $sender = trim((string) $this->message->author->profile?->display_name);
        $preview = Str::squish($this->message->content);

        return [
            'title' => $sender !== '' ? $sender : __('notifications.push.messages.title', locale: $locale),
            'body' => $preview !== ''
                ? Str::limit($preview, 100, '…')
                : __('notifications.push.messages.body', locale: $locale),
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
