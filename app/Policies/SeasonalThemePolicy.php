<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\SeasonalTheme;
use App\Models\User;

final class SeasonalThemePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::Admin);
    }

    public function update(User $user, SeasonalTheme $seasonalTheme): bool
    {
        return $user->hasRole(RoleName::Admin);
    }
}
