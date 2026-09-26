<?php

namespace App\Actions;

use App\Enums\PartnerAnnouncementStatus;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeletePartnerAnnouncementDraft
{
    public function handle(User $partner, PartnerAnnouncement $announcement): void
    {
        DB::transaction(function () use ($partner, $announcement): void {
            $profile = PartnerProfile::query()
                ->whereKey($announcement->partner_profile_id)
                ->lockForUpdate()
                ->firstOrFail();
            $locked = PartnerAnnouncement::query()
                ->whereKey($announcement->id)
                ->where('partner_profile_id', $profile->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($profile->user_id !== $partner->id) {
                throw new AuthorizationException;
            }

            if ($locked->status !== PartnerAnnouncementStatus::Draft) {
                throw ValidationException::withMessages([
                    'announcement' => __('partners.announcements.errors.not_draft'),
                ]);
            }

            $locked->delete();
        });
    }
}
