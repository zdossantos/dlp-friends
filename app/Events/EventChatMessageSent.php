<?php

namespace App\Events;

use App\Enums\UserStatus;
use App\Models\GroupChatMessage;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Gate;
use LogicException;

final class EventChatMessageSent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public GroupChatMessage $message) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        if (! $this->broadcastWhen()) {
            return [];
        }

        $chat = $this->message->groupChat;
        $recipients = User::query()->inGroupChat($chat)->whereKeyNot($this->message->author_user_id)
            ->get()
            ->filter(fn (User $user): bool => Gate::forUser($user)->allows('view', $chat));

        return [
            new PrivateChannel("event-chat.{$this->message->group_chat_id}"),
            ...$recipients->map(fn (User $user): PrivateChannel => new PrivateChannel("App.Models.User.{$user->id}"))->all(),
        ];
    }

    public function broadcastWhen(): bool
    {
        return User::query()->whereKey($this->message->author_user_id)->where('status', UserStatus::Active)->exists()
            && $this->message->groupChat()->where(fn ($q) => $q
                ->whereHas('event.organizer', fn ($owner) => $owner->where('status', UserStatus::Active))
                ->orWhereHas('trip.organizer', fn ($owner) => $owner->where('status', UserStatus::Active)))->exists();
    }

    public function broadcastAs(): string
    {
        return 'event-chat.message.sent';
    }

    /** @return array<string, bool|int|string|array{id: int, display_name: string}|null> */
    public function broadcastWith(): array
    {
        $this->message->loadMissing('author.profile');
        $profile = $this->message->author->profile
            ?? throw new LogicException('An event chat message requires an author profile.');

        return [
            'id' => $this->message->id,
            'group_chat_id' => $this->message->group_chat_id,
            'author_user_id' => $this->message->author_user_id,
            'content' => $this->message->content,
            'system_type' => $this->message->system_type?->value,
            'reaction_count' => 0,
            'reacted_by_current_user' => false,
            'author' => [
                'id' => $this->message->author->id,
                'display_name' => $profile->display_name,
            ],
            'created_at' => $this->message->created_at?->toISOString(),
            'updated_at' => $this->message->updated_at?->toISOString(),
        ];
    }
}
