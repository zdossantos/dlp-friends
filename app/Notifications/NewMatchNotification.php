<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\MemberMatch;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class NewMatchNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public MemberMatch $match, public User $otherMember)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database', 'broadcast'];
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
}
