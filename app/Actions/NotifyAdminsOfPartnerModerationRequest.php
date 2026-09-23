<?php

namespace App\Actions;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\PartnerModerationRequestedNotification;

final class NotifyAdminsOfPartnerModerationRequest
{
    /** @param array<string, string> $parameters */
    public function handle(
        string $translationKey,
        array $parameters,
        string $targetType,
        int $targetId,
    ): void {
        User::query()
            ->where('status', UserStatus::Active)
            ->whereHas('roles', fn ($query) => $query->where('name', RoleName::Admin))
            ->eachById(fn (User $admin) => $admin->notify(
                new PartnerModerationRequestedNotification(
                    $translationKey,
                    $parameters,
                    $targetType,
                    $targetId,
                ),
            ));
    }
}
