<?php

namespace App\Events;

use App\Models\GroupChatMessage;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Gate;

final class GroupChatMessageReactionUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public bool $afterCommit = true;

    public function __construct(
        public int $groupChatId,
        public int $messageId,
        public int $reactorUserId,
        public int $reactionCount,
        public bool $reacted,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        $message = $this->freshMessage();
        if ($message === null) {
            return [];
        }
        $chat = $message->groupChat;

        return User::query()->inGroupChat($chat)->get()
            ->filter(fn (User $user): bool => Gate::forUser($user)->allows('view', $chat))
            ->map(fn (User $user): PrivateChannel => new PrivateChannel("App.Models.User.{$user->id}"))->values()->all();
    }

    public function broadcastWhen(): bool
    {
        return $this->freshMessage() !== null;
    }

    public function broadcastAs(): string
    {
        return 'group-chat.message.reaction.updated';
    }

    /** @return array<string, int|bool> */
    public function broadcastWith(): array
    {
        $message = $this->freshMessage();
        if ($message === null) {
            return [];
        }

        return [
            'group_chat_id' => $this->groupChatId,
            'message_id' => $message->id,
            'reactor_user_id' => $this->reactorUserId,
            'reaction_count' => $message->reactions()->count(),
            'reacted' => $message->reactions()->where('user_id', $this->reactorUserId)->exists(),
        ];
    }

    private function freshMessage(): ?GroupChatMessage
    {
        $message = GroupChatMessage::query()->with('groupChat')->where('group_chat_id', $this->groupChatId)->find($this->messageId);
        $reactor = User::query()->find($this->reactorUserId);

        return $message !== null && $reactor !== null && Gate::forUser($reactor)->allows('view', $message->groupChat) ? $message : null;
    }
}
