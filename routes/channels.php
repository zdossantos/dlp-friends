<?php

use App\Enums\UserStatus;
use App\Models\Conversation;
use App\Models\GroupChat;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return $user->fresh()?->status === UserStatus::Active && (int) $user->id === (int) $id;
});

Broadcast::channel(
    'conversation.{conversation}',
    fn (User $user, Conversation $conversation): bool => Gate::forUser($user)
        ->allows('send', $conversation),
);

Broadcast::channel(
    'event-chat.{groupChat}',
    fn (User $user, GroupChat $groupChat): bool => Gate::forUser($user)
        ->allows('view', $groupChat),
);
