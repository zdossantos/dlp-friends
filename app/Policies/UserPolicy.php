<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(RoleName::Admin);
    }

    public function delete(User $actor, User $member): bool
    {
        return $actor->hasRole(RoleName::Admin)
            && ! $member->hasRole(RoleName::Admin);
    }

    public function startConversation(User $actor, User $member): bool
    {
        return $this->delete($actor, $member)
            && $member->status === UserStatus::Active
            && $member->profile?->isComplete() === true;
    }
}
