<?php

namespace App\Events;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class EventChatAccessChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public int $eventId,
        public int $userId,
        public string $access,
    ) {}

    /** @return PrivateChannel|array<never> */
    public function broadcastOn(): PrivateChannel|array
    {
        if (! $this->broadcastWhen()) {
            return [];
        }

        return new PrivateChannel("App.Models.User.{$this->userId}");
    }

    public function broadcastWhen(): bool
    {
        return User::query()->whereKey($this->userId)->where('status', UserStatus::Active)->exists();
    }

    public function broadcastAs(): string
    {
        return 'event-chat.access.changed';
    }

    /** @return array{event_id: int, access: string} */
    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->eventId,
            'access' => $this->access,
        ];
    }
}
