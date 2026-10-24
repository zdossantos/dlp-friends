<?php

namespace App\Policies;

use App\Models\GroupChat;
use App\Models\User;

class GroupChatPolicy
{
    public function view(User $user, GroupChat $chat): bool
    {
        $user = $user->fresh();

        return $user !== null && GroupChat::query()->visibleTo($user)->whereKey($chat->id)->exists();
    }

    public function react(User $user, GroupChat $chat): bool
    {
        return $chat->trip_id === null ? $this->view($user, $chat) : $this->send($user, $chat);
    }

    public function send(User $user, GroupChat $chat): bool
    {
        return $this->view($user, $chat) && ! $chat->isReadOnly();
    }
}
