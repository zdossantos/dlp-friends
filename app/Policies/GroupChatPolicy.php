<?php

namespace App\Policies;

use App\Enums\EventRegistrationStatus;
use App\Enums\UserStatus;
use App\Models\GroupChat;
use App\Models\User;

class GroupChatPolicy
{
    public function view(User $user, GroupChat $chat): bool
    {
        $user = $user->fresh();
        if ($user === null) {
            return false;
        }
        if ($chat->trip_id !== null) {
            $trip = $chat->trip()->first();

            return $trip !== null && app(TripPolicy::class)->viewPrivate($user, $trip);
        }
        $event = $chat->event()->first();
        if ($event === null || $event->organizer === null || $event->organizer->status !== UserStatus::Active) {
            return false;
        }

        return $user->status === UserStatus::Active
            && ($event->organizer_user_id === $user->id
            || $event->registrations()
                ->where('user_id', $user->id)
                ->where('status', EventRegistrationStatus::Accepted)
                ->exists());
    }

    public function send(User $user, GroupChat $chat): bool
    {
        return $this->view($user, $chat) && ! $chat->isReadOnly();
    }
}
