<?php

namespace App\Notifications;

use App\Contracts\PersonalizedWebPushNotification;
use App\Contracts\WebPushNotification;
use App\Enums\NotificationCategory;
use App\Enums\UserStatus;
use App\Enums\WebPushPreference;
use App\Models\GroupChatMessage;
use App\Models\User;
use App\Notifications\Channels\GroupMessageBroadcastChannel;
use App\Notifications\Channels\GroupMessageDatabaseChannel;
use App\Notifications\Channels\WebPushChannel;
use App\Support\WebPushTarget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

final class GroupChatMessageLikedNotification extends Notification implements PersonalizedWebPushNotification, ShouldQueue, WebPushNotification
{
    use Queueable;

    public int $messageId;

    public int $reactorId;

    public function __construct(GroupChatMessage $message, User $reactor)
    {
        $this->messageId = $message->id;
        $this->reactorId = $reactor->id;
        $this->afterCommit();
    }

    public function shouldSend(User $notifiable, string $channel): bool
    {
        $fresh = $notifiable->fresh();

        return $fresh !== null && $fresh->status === UserStatus::Active && $this->webPushAccessAllowed($fresh)
            && ($fresh->notificationPreferences()->where('category', WebPushPreference::GroupMessages)->value('enabled') ?? true);
    }

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return [GroupMessageDatabaseChannel::class, GroupMessageBroadcastChannel::class, WebPushChannel::class];
    }

    /** @return array{category: string, translation_key: string, parameters: array{member: string|null}, target_type: string, target_id: int} */
    public function toArray(User $notifiable): array
    {
        $reactor = User::query()->with('profile')->findOrFail($this->reactorId);
        $message = GroupChatMessage::query()->with('groupChat')->findOrFail($this->messageId);

        return [
            'category' => NotificationCategory::Conversations->value,
            'translation_key' => 'notifications.items.message_liked',
            'parameters' => ['member' => $reactor->profile?->display_name],
            'target_type' => $message->groupChat->trip_id !== null ? 'trip' : 'event',
            'target_id' => (int) ($message->groupChat->trip_id ?? $message->groupChat->event_id),
        ];
    }

    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(['id' => $this->id, ...$this->toArray($notifiable)]);
    }

    public function webPushPreference(): WebPushPreference
    {
        return WebPushPreference::GroupMessages;
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
        $message = GroupChatMessage::query()->with('groupChat')->findOrFail($this->messageId);

        return new WebPushTarget($message->groupChat->trip_id !== null
            ? '/trips/'.$message->groupChat->trip_id.'/chat'
            : route('events.chat.show', $message->groupChat->event_id, absolute: false));
    }

    public function webPushAccessAllowed(User $notifiable): bool
    {
        $message = GroupChatMessage::query()->with('groupChat')->find($this->messageId);
        $reactor = User::query()->find($this->reactorId);
        $recipient = $notifiable->fresh();

        return $message !== null && $reactor !== null && $recipient !== null
            && $recipient->id === $message->author_user_id
            && $recipient->status === UserStatus::Active && $reactor->status === UserStatus::Active
            && $message->reactions()->where('user_id', $reactor->id)->exists()
            && Gate::forUser($reactor)->allows('view', $message->groupChat)
            && Gate::forUser($recipient)->allows('view', $message->groupChat);
    }
}
