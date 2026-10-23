<?php

namespace App\Events;

use App\Models\User;
use App\Notifications\GroupChatMessageLikedNotification;
use App\Notifications\NewGroupMessageNotification;
use App\Notifications\TripLifecycleNotification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;

final class GroupMessageNotificationBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public function __construct(public int $recipientId, public NewGroupMessageNotification|GroupChatMessageLikedNotification|TripLifecycleNotification $notification) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return $this->recipient() === null ? [] : [new PrivateChannel('App.Models.User.'.$this->recipientId)];
    }

    public function broadcastWhen(): bool
    {
        return $this->recipient() !== null;
    }

    public function broadcastAs(): string
    {
        return BroadcastNotificationCreated::class;
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $recipient = $this->recipient();

        return $recipient === null ? [] : ['id' => $this->notification->id,
            'type' => $this->notification::class, ...$this->notification->toArray($recipient)];
    }

    private function recipient(): ?User
    {
        $recipient = User::query()->find($this->recipientId);

        return $recipient !== null && $this->notification->shouldSend($recipient, 'broadcast') ? $recipient : null;
    }
}
