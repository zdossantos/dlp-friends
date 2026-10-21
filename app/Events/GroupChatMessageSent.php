<?php

namespace App\Events;

use App\Data\GroupChatData;
use App\Enums\UserStatus;
use App\Models\GroupChatMessage;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Gate;

final class GroupChatMessageSent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public bool $afterCommit = true;

    public int $messageId;

    public function __construct(GroupChatMessage|int $message)
    {
        $this->messageId = $message instanceof GroupChatMessage ? $message->id : $message;
    }

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
        return 'group-chat.message.sent';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $message = $this->freshMessage();

        return $message === null ? [] : GroupChatData::message($message);
    }

    private function freshMessage(): ?GroupChatMessage
    {
        $message = GroupChatMessage::query()->with(['author', 'groupChat'])->find($this->messageId);
        $author = $message?->author()->first();
        if ($message === null || $author === null || $author->status !== UserStatus::Active) {
            return null;
        }
        $chat = $message->groupChat;
        $context = $chat->trip ?? $chat->event;
        if ($context?->organizer === null || $context->organizer->status !== UserStatus::Active) {
            return null;
        }
        if ($message->system_type === null && ! Gate::forUser($author)->allows('view', $chat)) {
            return null;
        }

        return $message;
    }
}
