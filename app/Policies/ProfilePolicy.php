<?php

namespace App\Policies;

use App\Enums\ProfileVisibility;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Profile;
use App\Models\Swipe;
use App\Models\User;

class ProfilePolicy
{
    public function update(User $user, Profile $profile): bool
    {
        return $profile->user_id === $user->id;
    }

    public function viewPublic(User $user, Profile $profile): bool
    {
        return $this->isPublicTarget($user, $profile);
    }

    public function viewPassed(User $user, Profile $profile): bool
    {
        return Swipe::query()->availablePassesFor($user)
            ->where('target_user_id', $profile->user_id)->exists();
    }

    public function block(User $user, Profile $profile): bool
    {
        return $this->isPublicTarget($user, $profile)
            && ! $profile->user->loadMissing('roles')->hasRole(RoleName::Admin);
    }

    private function isPublicTarget(User $user, Profile $profile): bool
    {
        $target = $profile->user;

        return $target->isNot($user)
            && $target->status === UserStatus::Active
            && $target->birth_date !== null
            && $target->birth_date->lte(today()->subYears(18))
            && $profile->visibility === ProfileVisibility::Visible
            && $profile->isComplete();
    }
}
