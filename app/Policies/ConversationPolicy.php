<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

final class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        $match = $conversation->memberMatch;

        return $match->user_low_id === $user->id
            || $match->user_high_id === $user->id;
    }

    public function send(User $user, Conversation $conversation): bool
    {
        return $conversation->archived_at === null
            && $this->view($user, $conversation);
    }
}
