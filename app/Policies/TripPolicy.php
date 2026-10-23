<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Enums\TripRegistrationStatus;
use App\Enums\UserStatus;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class TripPolicy
{
    public function create(User $user): bool
    {
        return $user->status === UserStatus::Active && $user->hasVerifiedEmail() && $user->age >= 18 && $user->hasRole(RoleName::User) && ($user->profile?->isComplete() ?? false);
    }

    public function view(User $user, Trip $trip): bool
    {
        return Trip::query()->visibleTo($user)->whereKey($trip->id)->exists();
    }

    public function viewPrivate(User $user, Trip $trip): bool
    {
        return $this->view($user, $trip) && ($trip->organizer_user_id === $user->id || $trip->registrations()->where('user_id', $user->id)->where('status', TripRegistrationStatus::Accepted)->exists());
    }

    public function manage(User $user, Trip $trip): bool
    {
        return $this->create($user) && $trip->organizer_user_id === $user->id;
    }

    public function request(User $user, Trip $trip): bool
    {
        return $this->view($user, $trip) && $trip->organizer_user_id !== $user->id && $trip->cancelled_at === null && $trip->departs_at->isFuture() && $trip->remainingPlaces() > 0
            && ! $trip->registrations()->where('status', TripRegistrationStatus::Accepted)
                ->whereHas('user', fn (Builder $participant) => $participant
                    ->whereHas('blocksCreated', fn (Builder $blocks) => $blocks->where('blocked_user_id', $user->id))
                    ->orWhereHas('blocksReceived', fn (Builder $blocks) => $blocks->where('blocker_user_id', $user->id)))
                ->exists();
    }
}
