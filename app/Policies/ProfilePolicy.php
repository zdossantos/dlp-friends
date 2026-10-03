<?php

namespace App\Policies;

use App\Enums\ProfileVisibility;
use App\Enums\RoleName;
use App\Enums\SocialLinksVisibility;
use App\Enums\UserStatus;
use App\Models\Block;
use App\Models\MemberMatch;
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

    public function viewSocialLinks(User $user, Profile $profile): bool
    {
        if ($profile->user_id === $user->id) {
            return true;
        }

        if (! $this->isPublicTarget($user, $profile)
            || $profile->social_links_visibility === SocialLinksVisibility::Hidden
            || Block::query()->whereIn('blocker_user_id', [$user->id, $profile->user_id])
                ->whereIn('blocked_user_id', [$user->id, $profile->user_id])->exists()) {
            return false;
        }

        return $profile->social_links_visibility === SocialLinksVisibility::Members
            || MemberMatch::query()
                ->where('user_low_id', min($user->id, $profile->user_id))
                ->where('user_high_id', max($user->id, $profile->user_id))
                ->exists();
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
