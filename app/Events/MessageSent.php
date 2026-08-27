<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MessageSent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public Message $message) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        $match = $this->message->conversation->memberMatch;

        return [
            new PrivateChannel("conversation.{$this->message->conversation_id}"),
            new PrivateChannel("App.Models.User.{$match->user_low_id}"),
            new PrivateChannel("App.Models.User.{$match->user_high_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /** @return array<string, int|string|null> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'author_user_id' => $this->message->author_user_id,
            'content' => $this->message->content,
            'read_at' => $this->message->read_at?->toISOString(),
            'created_at' => $this->message->created_at?->toISOString(),
            'updated_at' => $this->message->updated_at?->toISOString(),
        ];
    }
}
