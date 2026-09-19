<?php

namespace App\Actions;

use App\Enums\PartnerRevisionStatus;
use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RejectPartnerProfileRevision
{
    public function handle(
        User $admin,
        PartnerProfileRevision $revision,
        ?string $reason,
    ): void {
        DB::transaction(function () use ($admin, $revision, $reason): void {
            $profile = PartnerProfile::query()
                ->whereKey($revision->partner_profile_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedRevision = PartnerProfileRevision::query()
                ->whereKey($revision->id)
                ->where('partner_profile_id', $profile->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRevision->status !== PartnerRevisionStatus::PendingApproval) {
                throw ValidationException::withMessages([
                    'decision' => __('administration.partners.errors.not_pending'),
                ]);
            }

            $lockedRevision->update([
                'status' => PartnerRevisionStatus::Rejected,
                'decided_at' => now(),
                'decided_by' => $admin->id,
                'rejection_reason' => $reason,
                'expires_at' => now()->addYears(2),
            ]);
        });
    }
}
