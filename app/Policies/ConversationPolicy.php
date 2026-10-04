<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\Conversation;
use App\Models\User;

final class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        $match = $conversation->memberMatch;

        return $match->user_low_id !== null && $match->user_high_id !== null
            && ($match->user_low_id === $user->id || $match->user_high_id === $user->id);
    }

    public function send(User $user, Conversation $conversation): bool
    {
        $match = $conversation->memberMatch;

        return $match->lowUser !== null && $match->highUser !== null
            && $match->lowUser->status === UserStatus::Active
            && $match->highUser->status === UserStatus::Active
            && $conversation->archived_at === null
            && $this->view($user, $conversation)
            && ! $match->lowUser->hasBlockedRelationshipWith($match->highUser);
    }
}
