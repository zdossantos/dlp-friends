<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;

final class UserPolicy
{
    public function ban(User $actor, User $member): bool
    {
        return $actor->status === UserStatus::Active && $actor->hasRole(RoleName::Admin) && ! $member->hasRole(RoleName::Admin);
    }

    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(RoleName::Admin);
    }

    public function updateNewMemberAlerts(User $actor, User $member): bool
    {
        return $actor->is($member) && $actor->hasRole(RoleName::Admin);
    }

    public function delete(User $actor, User $member): bool
    {
        return $actor->hasRole(RoleName::Admin)
            && ! $member->hasRole(RoleName::Admin);
    }

    public function manageRoles(User $actor, User $member): bool
    {
        return $actor->hasRole(RoleName::Admin)
            && ! $actor->is($member);
    }

    public function startConversation(User $actor, User $member): bool
    {
        return $this->delete($actor, $member)
            && $member->status === UserStatus::Active
            && $member->profile?->isComplete() === true;
    }
}
