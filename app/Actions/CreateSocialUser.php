<?php

namespace App\Actions;

use App\Data\PendingSocialIdentity;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Exceptions\SocialAuthenticationException;
use App\Models\Role;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateSocialUser
{
    public function execute(PendingSocialIdentity $identity, string $birthDate): User
    {
        try {
            return DB::transaction(function () use ($identity, $birthDate): User {
                if (SocialAccount::query()
                    ->where('provider', $identity->provider->value)
                    ->where('provider_user_id', $identity->providerUserId)
                    ->exists()) {
                    throw new SocialAuthenticationException('social_auth.identity_conflict');
                }

                if (User::query()->whereRaw('LOWER(email) = ?', [$identity->email])->exists()) {
                    throw new SocialAuthenticationException('social_auth.email_conflict');
                }

                $user = new User([
                    'email' => $identity->email,
                    'birth_date' => $birthDate,
                    'password' => Str::password(64),
                ]);
                $user->forceFill([
                    'email_verified_at' => now(),
                    'status' => UserStatus::Active,
                ])->save();

                $role = Role::query()->where('name', RoleName::User)->firstOrFail();
                $user->roles()->attach($role);
                $user->socialAccounts()->create([
                    'provider' => $identity->provider->value,
                    'provider_user_id' => $identity->providerUserId,
                ]);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            throw new SocialAuthenticationException('social_auth.conflict');
        }
    }
}
