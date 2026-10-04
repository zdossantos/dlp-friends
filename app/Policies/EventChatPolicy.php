<?php

namespace App\Policies;

use App\Enums\EventRegistrationStatus;
use App\Enums\UserStatus;
use App\Models\EventChat;
use App\Models\User;

class EventChatPolicy
{
    public function view(User $user, EventChat $chat): bool
    {
        $event = $chat->event;
        if ($event->organizer === null || $event->organizer->status !== UserStatus::Active) {
            return false;
        }

        return $user->fresh()?->status === UserStatus::Active
            && ($event->organizer_user_id === $user->id
            || $event->registrations()
                ->where('user_id', $user->id)
                ->where('status', EventRegistrationStatus::Accepted)
                ->exists());
    }

    public function send(User $user, EventChat $chat): bool
    {
        return $this->view($user, $chat) && ! $chat->isReadOnly();
    }
}
