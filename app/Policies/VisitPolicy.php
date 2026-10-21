<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\Visit;

final class VisitPolicy
{
    public function view(User $user, Visit $visit): bool
    {
        return Visit::query()->visibleTo($user)->whereKey($visit->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->status === UserStatus::Active && $user->hasVerifiedEmail() && $user->age >= 18 && $user->hasRole(RoleName::User);
    }

    public function update(User $user, Visit $visit): bool
    {
        return $this->create($user) && $visit->user_id === $user->id;
    }

    public function delete(User $user, Visit $visit): bool
    {
        return $this->update($user, $visit);
    }
}
