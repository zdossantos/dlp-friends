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
use Illuminate\Support\Str;

final class NewGroupMessageNotification extends Notification implements PersonalizedWebPushNotification, ShouldQueue, WebPushNotification
{
    use Queueable;

    public int $messageId;

    public function __construct(GroupChatMessage $message)
    {
        $this->messageId = $message->id;
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
        return [GroupMessageDatabaseChannel::class, GroupMessageBroadcastChannel::class, WebPushChannel::class];
    }

    /** @return array{category: string, translation_key: string, parameters: array{sender: string|null, group: string}, target_type: string, target_id: int, message_id: int} */
    public function toArray(User $notifiable): array
    {
        $message = GroupChatMessage::query()->with(['author.profile', 'groupChat.event', 'groupChat.trip'])->findOrFail($this->messageId);

        return [
            'category' => NotificationCategory::Conversations->value,
            'translation_key' => 'notifications.items.new_group_message',
            'parameters' => ['sender' => $message->author->profile?->display_name, 'group' => ($message->groupChat->trip_id !== null ? $message->groupChat->trip()->firstOrFail()->destination : $message->groupChat->event()->firstOrFail()->title)],
            'target_type' => $message->groupChat->trip_id !== null ? 'trip_chat' : 'event_chat',
            'target_id' => (int) ($message->groupChat->trip_id ?? $message->groupChat->event_id),
            'message_id' => $message->id,
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
        $message = GroupChatMessage::query()->with('author.profile')->findOrFail($this->messageId);

        $sender = trim((string) $message->author->profile?->display_name);
        $preview = Str::squish($message->content);

        return [
            'title' => $sender !== '' ? $sender : __('notifications.push.messages.title', locale: $locale),
            'body' => $preview !== ''
                ? Str::limit($preview, 100, '…')
                : __('notifications.push.messages.body', locale: $locale),
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
        $message = GroupChatMessage::query()->with(['author', 'groupChat'])->find($this->messageId);
        $recipient = $notifiable->fresh();
        if ($message === null || $recipient === null || $recipient->status !== UserStatus::Active) {
            return false;
        }

        return $recipient->id !== $message->author_user_id
            && $message->author->status === UserStatus::Active
            && Gate::forUser($message->author)->allows('view', $message->groupChat)
            && Gate::forUser($recipient)->allows('view', $message->groupChat)
            && ! $message->groupChat->reads()->where('user_id', $recipient->id)
                ->where('last_read_message_id', '>=', $message->id)->exists();
    }
}
