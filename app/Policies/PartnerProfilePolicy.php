<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use App\Models\User;

final class PartnerProfilePolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Partner);
    }

    public function view(User $user, PartnerProfile $profile): bool
    {
        return $this->owns($user, $profile);
    }

    public function update(User $user, PartnerProfile $profile): bool
    {
        return $this->owns($user, $profile);
    }

    public function submit(User $user, PartnerProfile $profile): bool
    {
        return $this->owns($user, $profile);
    }

    public function viewRevision(
        User $user,
        PartnerProfile $profile,
        PartnerProfileRevision $revision,
    ): bool {
        return $user->hasRole(RoleName::Admin)
            || ($this->owns($user, $profile) && $revision->partner_profile_id === $profile->id);
    }

    private function owns(User $user, PartnerProfile $profile): bool
    {
        return $user->hasRole(RoleName::Partner)
            && $profile->user_id === $user->id;
    }
}
