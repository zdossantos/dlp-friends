<?php

namespace App\Enums;

use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as SocialiteUser;

enum SocialProvider: string
{
    case Google = 'google';

    public function hasVerifiedEmail(SocialiteUser $user): bool
    {
        if (! $user instanceof AbstractUser) {
            return false;
        }

        return filter_var(data_get($user->getRaw(), 'verified_email'), FILTER_VALIDATE_BOOL);
    }
}
