<?php

namespace App\Policies;

use App\Enums\EventRegistrationStatus;
use App\Models\Event;
use App\Models\User;

final class EventPolicy
{
    public function view(User $user, Event $event): bool
    {
        return $event->organizer_user_id === $user->id
            || ! $user->hasBlockedRelationshipWith($event->organizer);
    }

    public function viewPrivateDetails(User $user, Event $event): bool
    {
        return $event->organizer_user_id === $user->id
            || $event->registrations()
                ->where('user_id', $user->id)
                ->where('status', EventRegistrationStatus::Accepted)
                ->exists();
    }

    public function update(User $user, Event $event): bool
    {
        return $event->organizer_user_id === $user->id;
    }
}
