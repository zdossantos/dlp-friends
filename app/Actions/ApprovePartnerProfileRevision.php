<?php

namespace App\Actions;

use App\Enums\PartnerRevisionStatus;
use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ApprovePartnerProfileRevision
{
    public function handle(User $admin, PartnerProfileRevision $revision): void
    {
        DB::transaction(function () use ($admin, $revision): void {
            $profile = PartnerProfile::query()
                ->whereKey($revision->partner_profile_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedRevision = PartnerProfileRevision::query()
                ->whereKey($revision->id)
                ->where('partner_profile_id', $profile->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensurePending($lockedRevision);

            $position = $profile->position;

            if (! $profile->is_published) {
                $position = ((int) PartnerProfile::query()->published()->max('position')) + 1;
            }

            $lockedRevision->update([
                'status' => PartnerRevisionStatus::Approved,
                'decided_at' => now(),
                'decided_by' => $admin->id,
                'rejection_reason' => null,
                'expires_at' => now()->addYears(2),
            ]);
            $profile->update([
                'published_revision_id' => $lockedRevision->id,
                'is_published' => true,
                'position' => $position,
            ]);
        });
    }

    public function unpublish(PartnerProfile $profile): void
    {
        DB::transaction(function () use ($profile): void {
            $lockedProfile = PartnerProfile::query()
                ->whereKey($profile->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedProfile->is_published) {
                throw ValidationException::withMessages([
                    'profile' => __('administration.partners.errors.not_published'),
                ]);
            }

            $lockedProfile->update(['is_published' => false]);

            PartnerProfile::query()
                ->published()
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->each(fn (PartnerProfile $published, int $index) => $published->update([
                    'position' => $index + 1,
                ]));
        });
    }

    private function ensurePending(PartnerProfileRevision $revision): void
    {
        if ($revision->status !== PartnerRevisionStatus::PendingApproval) {
            throw ValidationException::withMessages([
                'decision' => __('administration.partners.errors.not_pending'),
            ]);
        }
    }
}
