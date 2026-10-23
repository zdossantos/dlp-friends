<?php

namespace App\Events;

use App\Enums\UserStatus;
use App\Models\GroupChat;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Gate;

final class GroupChatAccessChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public bool $afterCommit = true;

    public function __construct(public int $groupChatId, public int $userId, public string $access) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return $this->broadcastWhen() ? [new PrivateChannel("App.Models.User.{$this->userId}")] : [];
    }

    public function broadcastWhen(): bool
    {
        return User::query()->whereKey($this->userId)->where('status', UserStatus::Active)->exists()
            && GroupChat::query()->whereKey($this->groupChatId)->exists();
    }

    public function broadcastAs(): string
    {
        return 'group-chat.access.changed';
    }

    /** @return array<string, int|string|null> */
    public function broadcastWith(): array
    {
        $chat = GroupChat::query()->find($this->groupChatId);
        $user = User::query()->find($this->userId);
        if ($chat === null || $user === null || ! $this->broadcastWhen()) {
            return [];
        }

        return [
            'group_chat_id' => $chat->id,
            'context' => $chat->trip_id !== null ? 'trip' : 'event',
            'context_id' => $chat->trip_id ?? $chat->event_id,
            'access' => Gate::forUser($user)->allows('view', $chat) ? ($chat->isReadOnly() ? 'read_only' : 'granted') : 'revoked',
        ];
    }
}
