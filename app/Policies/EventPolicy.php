<?php

namespace App\Policies;

use App\Enums\EventRegistrationStatus;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Event;
use App\Models\User;

final class EventPolicy
{
    public function register(User $user, Event $event): bool
    {
        return $user->status === UserStatus::Active && $user->hasVerifiedEmail()
            && $user->age >= 18 && $user->hasRole(RoleName::User)
            && ($user->profile?->isComplete() ?? false)
            && $event->organizer?->status === UserStatus::Active
            && $event->organizer->hasVerifiedEmail() && $event->organizer->hasRole(RoleName::User);
    }

    public function view(User $user, Event $event): bool
    {
        return Event::query()->visibleTo($user)->whereKey($event->id)->exists();
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
