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
            $decidedAt = now();
            $expiresAt = $decidedAt->copy()->addYears(2);

            $revisions = PartnerProfileRevision::query()
                ->where('partner_profile_id', $profile->id)
                ->where('status', '!=', PartnerRevisionStatus::Draft->value)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($revisions as $revision) {
                $revision->update([
                    'status' => $revision->status === PartnerRevisionStatus::PendingApproval
                        ? PartnerRevisionStatus::Rejected
                        : $revision->status,
                    'decided_at' => $revision->status === PartnerRevisionStatus::PendingApproval
                        ? $decidedAt
                        : $revision->decided_at,
                    'decided_by' => $revision->status === PartnerRevisionStatus::PendingApproval
                        ? null
                        : $revision->decided_by,
                    'rejection_reason' => $revision->status === PartnerRevisionStatus::PendingApproval
                        ? null
                        : $revision->rejection_reason,
                    'expires_at' => $revision->expires_at ?? $expiresAt,
                ]);
            }

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
                $announcementExpiresAt = $announcement->expires_at ?? $expiresAt;
                $announcement->update([
                    'status' => PartnerAnnouncementStatus::Cancelled,
                    'decided_at' => $announcement->decided_at ?? $decidedAt,
                    'expires_at' => $announcementExpiresAt,
                ]);

                $pendingDeliveries = PartnerAnnouncementDelivery::query()
                    ->where('partner_announcement_id', $announcement->id)
                    ->where('status', PartnerDeliveryStatus::Pending)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($pendingDeliveries as $delivery) {
                    $delivery->update([
                        'status' => PartnerDeliveryStatus::Skipped,
                        'last_error' => null,
                    ]);
                }

                $announcement->metric()->update(['expires_at' => $announcementExpiresAt]);
            }
        });
    }
}
