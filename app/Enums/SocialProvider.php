<?php

namespace App\Enums;

use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as SocialiteUser;

enum SocialProvider: string
{
    case Google = 'google';
    case Apple = 'apple';

    public function hasVerifiedEmail(SocialiteUser $user): bool
    {
        if (! $user instanceof AbstractUser) {
            return false;
        }

        $key = $this === self::Google ? 'verified_email' : 'email_verified';

        return filter_var(data_get($user->getRaw(), $key), FILTER_VALIDATE_BOOL);
    }
}
