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
use LogicException;

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

    /** @return array{match_id: int, conversation_id: int, member: array{id: int, displayName: string, avatar: array{id: int, name: string, image_url: string, primary_color: string, secondary_color: string}}} */
    public function broadcastWith(): array
    {
        $this->memberMatch->loadMissing([
            'conversation',
            'lowUser.profile.avatar',
            'highUser.profile.avatar',
        ]);
        $member = $this->memberMatch->lowUser->is($this->recipient)
            ? $this->memberMatch->highUser
            : $this->memberMatch->lowUser;
        $profile = $member->profile
            ?? throw new LogicException('A match notification requires a member profile.');
        $avatar = $profile->avatar
            ?? throw new LogicException('A match notification requires a member avatar.');

        return [
            'match_id' => $this->memberMatch->id,
            'conversation_id' => $this->memberMatch->conversation?->id
                ?? throw new LogicException('A match notification requires a conversation.'),
            'member' => [
                'id' => $member->id,
                'displayName' => $profile->display_name,
                'avatar' => [
                    'id' => $avatar->id,
                    'name' => $avatar->name,
                    'image_url' => route('avatars.image', $avatar),
                    'primary_color' => $avatar->primary_color,
                    'secondary_color' => $avatar->secondary_color,
                ],
            ],
        ];
    }
}
