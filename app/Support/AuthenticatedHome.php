<?php

namespace App\Support;

use App\Enums\RoleName;
use App\Models\User;

final class AuthenticatedHome
{
    public static function routeName(?User $user): string
    {
        if ($user === null || $user->hasRole(RoleName::User)) {
            return 'app';
        }

        if ($user->hasRole(RoleName::Admin)) {
            return 'dashboard';
        }

        if ($user->hasRole(RoleName::Partner)) {
            return 'partner.profile.edit';
        }

        return 'app';
    }

    public static function url(?User $user): string
    {
        return route(self::routeName($user));
    }
}
