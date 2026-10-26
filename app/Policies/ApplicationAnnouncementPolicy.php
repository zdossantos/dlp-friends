<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;

final class ApplicationAnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::Admin) && $user->status === UserStatus::Active
            && $user->email_verified_at !== null && $user->deletion_requested_at === null;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }
}
