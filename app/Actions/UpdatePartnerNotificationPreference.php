<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdatePartnerNotificationPreference
{
    public function handle(User $user, bool $enabled): void
    {
        DB::transaction(function () use ($user, $enabled): void {
            // Eligibility mutations lock the user before related eligibility rows.
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            $lockedUser->partnerNotificationPreference()->updateOrCreate([], [
                'enabled' => $enabled,
            ]);
        });
    }
}
