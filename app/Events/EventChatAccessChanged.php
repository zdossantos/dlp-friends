<?php

namespace App\Events;

use App\Models\GroupChat;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class EventChatAccessChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public bool $afterCommit = true;

    public function __construct(public int $eventId, public int $userId, public string $access) {}

    private function common(): GroupChatAccessChanged
    {
        return new GroupChatAccessChanged((int) GroupChat::query()->where('event_id', $this->eventId)->value('id'), $this->userId, $this->access);
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return $this->common()->broadcastOn();
    }

    public function broadcastWhen(): bool
    {
        return $this->common()->broadcastWhen();
    }

    public function broadcastAs(): string
    {
        return $this->common()->broadcastAs();
    }

    /** @return array<string, int|string|null> */
    public function broadcastWith(): array
    {
        return $this->common()->broadcastWith();
    }
}
