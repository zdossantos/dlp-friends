<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Gate;

final class MessageReactionUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public bool $afterCommit = true;

    public function __construct(
        public int $conversationId,
        public int $messageId,
        public int $reactorUserId,
        public int $reactionCount,
        public bool $reacted,
    ) {}

    public function broadcastOn(): array
    {
        if (! $this->broadcastWhen()) {
            return [];
        }

        return [new PrivateChannel("conversation.{$this->conversationId}")];
    }

    public function broadcastWhen(): bool
    {
        $conversation = Conversation::query()->with(['memberMatch.lowUser', 'memberMatch.highUser'])->find($this->conversationId);
        $reactor = User::query()->find($this->reactorUserId);

        return $conversation !== null && $reactor !== null
            && Gate::forUser($reactor)->allows('send', $conversation);
    }

    public function broadcastAs(): string
    {
        return 'message.reaction.updated';
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
