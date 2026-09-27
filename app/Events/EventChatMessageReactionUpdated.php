<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class EventChatMessageReactionUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public bool $afterCommit = true;

    public function __construct(
        public int $eventChatId,
        public int $messageId,
        public int $reactorUserId,
        public int $reactionCount,
        public bool $reacted,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("event-chat.{$this->eventChatId}")];
    }

    public function broadcastAs(): string
    {
        return 'event-chat.message.reaction.updated';
    }

    /** @return array{message_id: int, reactor_user_id: int, reaction_count: int, reacted: bool} */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'reactor_user_id' => $this->reactorUserId,
            'reaction_count' => $this->reactionCount,
            'reacted' => $this->reacted,
        ];
    }
}
