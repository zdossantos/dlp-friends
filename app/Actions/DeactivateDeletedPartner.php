<?php

namespace App\Actions;

use App\Enums\PartnerAnnouncementStatus;
use App\Enums\PartnerDeliveryStatus;
use App\Enums\PartnerRevisionStatus;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerAnnouncementDelivery;
use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DeactivateDeletedPartner
{
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $profile = PartnerProfile::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($profile === null) {
                return;
            }

            $profile->update(['is_published' => false]);

            PartnerProfileRevision::query()
                ->where('partner_profile_id', $profile->id)
                ->where('status', '!=', PartnerRevisionStatus::Draft->value)
                ->whereNull('expires_at')
                ->update(['expires_at' => now()->addYears(2)]);

            $announcements = PartnerAnnouncement::query()
                ->where('partner_profile_id', $profile->id)
                ->whereIn('status', [
                    PartnerAnnouncementStatus::Draft,
                    PartnerAnnouncementStatus::PendingApproval,
                    PartnerAnnouncementStatus::Approved,
                    PartnerAnnouncementStatus::Sending,
                ])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($announcements as $announcement) {
                $expiresAt = $announcement->expires_at ?? now()->addYears(2);
                $announcement->update([
                    'status' => PartnerAnnouncementStatus::Cancelled,
                    'decided_at' => $announcement->decided_at ?? now(),
                    'expires_at' => $expiresAt,
                ]);
                $announcement->metric()->update(['expires_at' => $expiresAt]);

                PartnerAnnouncementDelivery::query()
                    ->where('partner_announcement_id', $announcement->id)
                    ->where('status', PartnerDeliveryStatus::Pending)
                    ->update([
                        'status' => PartnerDeliveryStatus::Skipped,
                        'last_error' => null,
                    ]);
            }
        });
    }
}
