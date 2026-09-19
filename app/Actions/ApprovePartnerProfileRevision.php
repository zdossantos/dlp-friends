<?php

namespace App\Actions;

use App\Enums\PartnerRevisionStatus;
use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ApprovePartnerProfileRevision
{
    public function __construct(private readonly LockPartnerProfileOrder $lockPartnerProfileOrder) {}

    public function handle(User $admin, PartnerProfileRevision $revision): void
    {
        DB::transaction(function () use ($admin, $revision): void {
            $profiles = $this->lockPartnerProfileOrder->handle();
            $profile = $profiles->first(
                fn (PartnerProfile $candidate): bool => $candidate->id === $revision->partner_profile_id,
            );

            if (! $profile instanceof PartnerProfile) {
                throw (new ModelNotFoundException)->setModel(
                    PartnerProfile::class,
                    [$revision->partner_profile_id],
                );
            }

            $lockedRevision = PartnerProfileRevision::query()
                ->whereKey($revision->id)
                ->where('partner_profile_id', $profile->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensurePending($lockedRevision);

            $position = $profile->position;

            if (! $profile->is_published) {
                $position = ((int) $profiles
                    ->filter(fn (PartnerProfile $candidate): bool => $candidate->is_published
                        && $candidate->published_revision_id !== null)
                    ->max('position')) + 1;
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
            $profiles = $this->lockPartnerProfileOrder->handle();
            $lockedProfile = $profiles->first(
                fn (PartnerProfile $candidate): bool => $candidate->id === $profile->id,
            );

            if (! $lockedProfile instanceof PartnerProfile) {
                throw (new ModelNotFoundException)->setModel(PartnerProfile::class, [$profile->id]);
            }

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
