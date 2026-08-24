<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Interest;
use App\Models\User;

final class InterestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Admin);
    }

    public function update(User $user, Interest $interest): bool
    {
        return $user->hasRole(RoleName::Admin);
    }

    public function delete(User $user, Interest $interest): bool
    {
        return $user->hasRole(RoleName::Admin);
    }
}
