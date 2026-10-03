<?php

namespace App\Events;

use App\Models\User;
use App\Notifications\NewMemberNotification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;

final class NewMemberNotificationBroadcast extends BroadcastNotificationCreated
{
    public function __construct(User $recipient, private readonly NewMemberNotification $arrival)
    {
        parent::__construct($recipient, $arrival, $arrival->toBroadcast($recipient)->data);
    }

    /** @return array<PrivateChannel> */
    public function broadcastOn(): array
    {
        $recipient = $this->notifiable->fresh();

        return $recipient !== null && $this->arrival->webPushAccessAllowed($recipient)
            ? parent::broadcastOn() : [];
    }

    public function broadcastAs(): string
    {
        return BroadcastNotificationCreated::class;
    }
}
