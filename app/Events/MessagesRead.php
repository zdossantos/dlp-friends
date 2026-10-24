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

final class MessagesRead implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $conversationId,
        public int $readerUserId,
        public int $lastReadMessageId,
        public string $readAt,
    ) {}

    /** @return PrivateChannel|array<never> */
    public function broadcastOn(): PrivateChannel|array
    {
        if (! $this->broadcastWhen()) {
            return [];
        }

        return new PrivateChannel("conversation.{$this->conversationId}");
    }

    public function broadcastWhen(): bool
    {
        $conversation = Conversation::query()->with(['memberMatch.lowUser', 'memberMatch.highUser'])->find($this->conversationId);
        $reader = User::query()->find($this->readerUserId);

        return $conversation !== null && $reader !== null
            && Gate::forUser($reader)->allows('send', $conversation);
    }

    public function broadcastAs(): string
    {
        return 'messages.read';
    }

    /** @return array{conversation_id: int, reader_user_id: int, last_read_message_id: int, read_at: string} */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'reader_user_id' => $this->readerUserId,
            'last_read_message_id' => $this->lastReadMessageId,
            'read_at' => $this->readAt,
        ];
    }
}
