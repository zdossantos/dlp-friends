<?php

namespace App\Notifications;

use App\Contracts\PersonalizedWebPushNotification;
use App\Contracts\WebPushNotification;
use App\Enums\NotificationCategory;
use App\Enums\WebPushPreference;
use App\Models\MemberMatch;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Support\WebPushTarget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class NewMatchNotification extends Notification implements PersonalizedWebPushNotification, ShouldQueue, WebPushNotification
{
    use Queueable;

    public function __construct(public MemberMatch $match, public User $otherMember)
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
        $this->match->loadMissing('conversation');
        $this->otherMember->loadMissing('profile');

        return [
            'category' => NotificationCategory::Conversations->value,
            'translation_key' => 'notifications.items.new_match',
            'parameters' => ['member' => $this->otherMember->profile?->display_name],
            'target_type' => 'conversation',
            'target_id' => (int) $this->match->conversation?->id,
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
        return WebPushPreference::Matches;
    }

    /** @return array{title: string, body: string} */
    public function webPushCopy(User $notifiable, string $locale): array
    {
        $this->otherMember->loadMissing('profile');
        $member = trim((string) $this->otherMember->profile?->display_name);

        return [
            'title' => __('notifications.push.matches.title', locale: $locale),
            'body' => $member !== ''
                ? $member
                : __('notifications.push.matches.body', locale: $locale),
        ];
    }

    public function webPushTarget(User $notifiable): WebPushTarget
    {
        $this->match->loadMissing('conversation');

        return $this->match->conversation === null
            ? WebPushTarget::fallback()
            : new WebPushTarget(route('conversations.show', $this->match->conversation, absolute: false));
    }

    public function webPushAccessAllowed(User $notifiable): bool
    {
        $this->match->loadMissing('conversation');

        return $this->match->conversation?->newQuery()->whereKey($this->match->conversation->id)
            ->forMember($notifiable)->withUnblockedParticipant($notifiable)->exists() ?? false;
    }
}
