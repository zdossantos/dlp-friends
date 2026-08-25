<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Avatar;
use App\Models\User;

final class AvatarPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Admin);
    }

    public function update(User $user, Avatar $avatar): bool
    {
        return $user->hasRole(RoleName::Admin);
    }

    public function delete(User $user, Avatar $avatar): bool
    {
        return $user->hasRole(RoleName::Admin);
    }
}
