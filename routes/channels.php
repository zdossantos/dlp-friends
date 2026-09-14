<?php

use App\Models\Conversation;
use App\Models\EventChat;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel(
    'conversation.{conversation}',
    fn (User $user, Conversation $conversation): bool => Gate::forUser($user)
        ->allows('send', $conversation),
);

Broadcast::channel(
    'event-chat.{eventChat}',
    fn (User $user, EventChat $eventChat): bool => Gate::forUser($user)
        ->allows('view', $eventChat),
);
