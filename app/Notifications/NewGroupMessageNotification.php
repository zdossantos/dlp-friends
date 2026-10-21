<?php

namespace App\Notifications;

use App\Contracts\PersonalizedWebPushNotification;
use App\Contracts\WebPushNotification;
use App\Enums\NotificationCategory;
use App\Enums\UserStatus;
use App\Enums\WebPushPreference;
use App\Models\GroupChatMessage;
use App\Models\User;
use App\Notifications\Channels\GroupMessageDatabaseChannel;
use App\Notifications\Channels\WebPushChannel;
use App\Support\WebPushTarget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class NewGroupMessageNotification extends Notification implements PersonalizedWebPushNotification, ShouldQueue, WebPushNotification
{
    use Queueable;

    public function __construct(public GroupChatMessage $message)
    {
        $this->afterCommit();
    }

    public function shouldSend(User $notifiable, string $channel): bool
    {
        $fresh = $notifiable->fresh();

        return $fresh !== null && $fresh->status === UserStatus::Active
            && $this->webPushAccessAllowed($fresh)
            && ($fresh->notificationPreferences()->where('category', WebPushPreference::GroupMessages)->value('enabled') ?? true);
    }

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return [GroupMessageDatabaseChannel::class, 'broadcast', WebPushChannel::class];
    }

    /** @return array{category: string, translation_key: string, parameters: array{sender: string|null, group: string}, target_type: string, target_id: int, message_id: int} */
    public function toArray(User $notifiable): array
    {
        $this->message->loadMissing(['author.profile', 'groupChat.event']);

        return [
            'category' => NotificationCategory::Conversations->value,
            'translation_key' => 'notifications.items.new_group_message',
            'parameters' => ['sender' => $this->message->author->profile?->display_name, 'group' => ($this->message->groupChat->trip_id !== null ? $this->message->groupChat->trip()->firstOrFail()->destination : $this->message->groupChat->event()->firstOrFail()->title)],
            'target_type' => $this->message->groupChat->trip_id !== null ? 'trip_chat' : 'event_chat',
            'target_id' => (int) ($this->message->groupChat->trip_id ?? $this->message->groupChat->event_id),
            'message_id' => $this->message->id,
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
        return WebPushPreference::GroupMessages;
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
        return new WebPushTarget($this->message->groupChat->trip_id !== null
            ? '/trips/'.$this->message->groupChat->trip_id.'/chat'
            : route('events.chat.show', $this->message->groupChat->event_id, absolute: false));
    }

    public function webPushAccessAllowed(User $notifiable): bool
    {
        return $notifiable->id !== $this->message->author_user_id
            && $this->message->author()->where('status', UserStatus::Active)->exists()
            && Gate::forUser($notifiable)->allows('view', $this->message->groupChat)
            && ! $this->message->groupChat->reads()->where('user_id', $notifiable->id)
                ->where('last_read_message_id', '>=', $this->message->id)->exists();
    }
}
