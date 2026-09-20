<?php

namespace App\Actions;

use App\Enums\PartnerRevisionStatus;
use App\Models\PartnerAnnouncementDelivery;
use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class PurgeDeletedPartnerData
{
    public function handle(User $user): void
    {
        $profile = PartnerProfile::query()
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->first();

        $user->partnerNotificationPreference()->delete();
        $user->partnerAnnouncementDeliveries()->delete();

        if ($profile === null) {
            return;
        }

        PartnerAnnouncementDelivery::query()
            ->whereHas(
                'announcement',
                fn ($announcements) => $announcements->where('partner_profile_id', $profile->id),
            )
            ->delete();

        $drafts = PartnerProfileRevision::query()
            ->where('partner_profile_id', $profile->id)
            ->where('status', PartnerRevisionStatus::Draft)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $paths = $drafts->pluck('image_path')->filter()->unique()->values();

        PartnerProfileRevision::query()
            ->whereKey($drafts->modelKeys())
            ->delete();

        foreach ($paths as $path) {
            if (PartnerProfileRevision::query()->where('image_path', $path)->exists()) {
                continue;
            }

            DB::afterCommit(fn () => Storage::delete($path));
        }
    }
}
