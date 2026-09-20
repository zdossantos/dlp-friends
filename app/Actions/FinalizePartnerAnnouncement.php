<?php

namespace App\Actions;

use App\Enums\PartnerAnnouncementStatus;
use App\Enums\PartnerDeliveryStatus;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerProfile;
use Illuminate\Support\Facades\DB;

final class FinalizePartnerAnnouncement
{
    public function handle(PartnerAnnouncement $announcement): void
    {
        DB::transaction(function () use ($announcement): void {
            $profile = PartnerProfile::query()
                ->whereKey($announcement->partner_profile_id)
                ->lockForUpdate()
                ->firstOrFail();
            $locked = PartnerAnnouncement::query()
                ->whereKey($announcement->id)
                ->where('partner_profile_id', $profile->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== PartnerAnnouncementStatus::Sending
                || $locked->audience_prepared_at === null
                || $locked->deliveries()->whereIn('status', [
                    PartnerDeliveryStatus::Pending,
                    PartnerDeliveryStatus::Failed,
                ])->exists()) {
                return;
            }

            $locked->update([
                'status' => PartnerAnnouncementStatus::Sent,
                'sent_at' => now(),
            ]);
        });
    }
}
