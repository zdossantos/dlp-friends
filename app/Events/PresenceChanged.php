<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PresenceChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public User $member, public bool $online) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return Conversation::query()
            ->forMember($this->member)
            ->whereNull('archived_at')
            ->with(['memberMatch.lowUser', 'memberMatch.highUser'])
            ->get()
            ->map(function (Conversation $conversation): ?User {
                $match = $conversation->memberMatch;
                $peer = $match->lowUser->is($this->member) ? $match->highUser : $match->lowUser;

                return $this->member->hasBlockedRelationshipWith($peer) ? null : $peer;
            })
            ->filter()
            ->unique('id')
            ->map(fn (User $peer): PrivateChannel => new PrivateChannel("App.Models.User.{$peer->id}"))
            ->values()
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'presence.changed';
    }

    /** @return array{user_id: int, online: bool, last_active_at: string|null, expires_at: string|null} */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->member->id,
            'online' => $this->online,
            'last_active_at' => $this->member->last_active_at?->toISOString(),
            'expires_at' => $this->online ? now()->addSeconds(45)->toISOString() : null,
        ];
    }
}
