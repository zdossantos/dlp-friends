<?php

namespace App\Events;

use App\Models\EventChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LogicException;

final class EventChatMessageSent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public EventChatMessage $message) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("event-chat.{$this->message->event_chat_id}")];
    }

    public function broadcastAs(): string
    {
        return 'event-chat.message.sent';
    }

    /** @return array<string, int|string|array{id: int, display_name: string}|null> */
    public function broadcastWith(): array
    {
        $this->message->loadMissing('author.profile');
        $profile = $this->message->author->profile
            ?? throw new LogicException('An event chat message requires an author profile.');

        return [
            'id' => $this->message->id,
            'event_chat_id' => $this->message->event_chat_id,
            'author_user_id' => $this->message->author_user_id,
            'content' => $this->message->content,
            'author' => [
                'id' => $this->message->author->id,
                'display_name' => $profile->display_name,
            ],
            'created_at' => $this->message->created_at?->toISOString(),
            'updated_at' => $this->message->updated_at?->toISOString(),
        ];
    }
}
