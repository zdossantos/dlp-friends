<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\PartnerAnnouncement;
use App\Models\User;

final class PartnerAnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::Partner);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Partner) && $user->partnerProfile !== null;
    }

    public function view(User $user, PartnerAnnouncement $announcement): bool
    {
        return $this->owns($user, $announcement);
    }

    public function update(User $user, PartnerAnnouncement $announcement): bool
    {
        return $this->owns($user, $announcement);
    }

    public function delete(User $user, PartnerAnnouncement $announcement): bool
    {
        return $this->owns($user, $announcement);
    }

    public function submit(User $user, PartnerAnnouncement $announcement): bool
    {
        return $this->owns($user, $announcement);
    }

    public function cancel(User $user, PartnerAnnouncement $announcement): bool
    {
        return $this->owns($user, $announcement);
    }

    private function owns(User $user, PartnerAnnouncement $announcement): bool
    {
        return $user->hasRole(RoleName::Partner)
            && $announcement->partnerProfile->user_id === $user->id;
    }
}
