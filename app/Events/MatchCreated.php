<?php

namespace App\Events;

use App\Models\MemberMatch;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MatchCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public MemberMatch $memberMatch,
        public User $recipient,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("App.Models.User.{$this->recipient->id}");
    }

    public function broadcastAs(): string
    {
        return 'match.created';
    }

    /** @return array{match_id: int, conversation_id: int} */
    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->memberMatch->id,
            'conversation_id' => $this->memberMatch->conversation?->id
                ?? throw new \LogicException('A match notification requires a conversation.'),
        ];
    }
}
