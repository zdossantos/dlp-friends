<?php

namespace App\Actions;

use App\Enums\PartnerAnnouncementStatus;
use App\Enums\RoleName;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerProfile;
use App\Models\User;
use App\Notifications\PartnerAnnouncementDecisionNotification;
use App\Rules\SafeHttpsUrl;
use App\Support\PartnerAnnouncementCooldown;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class DecidePartnerAnnouncement
{
    public function __construct(
        private readonly StartPartnerAnnouncement $startAnnouncement,
        private readonly PartnerAnnouncementCooldown $cooldown,
    ) {}

    public function approve(User $admin, PartnerAnnouncement $announcement): void
    {
        $this->ensureAdmin($admin);

        DB::transaction(function () use ($admin, $announcement): void {
            $this->cooldown->acquireGlobalLock();

            [$profile, $locked] = $this->lock($announcement);
            $this->ensurePending($locked);

            Validator::make(
                ['destination_url' => $locked->destination_url],
                ['destination_url' => ['required', 'string', 'max:2048', new SafeHttpsUrl]],
            )->validate();

            $availableAt = $this->cooldown->nextAvailableAt(
                $profile->id,
                $locked->id,
                lockForUpdate: true,
            );

            if ($availableAt !== null) {
                throw ValidationException::withMessages([
                    'decision' => __('administration.partner_announcements.errors.cooldown', [
                        'date' => $this->cooldown->formatted($availableAt),
                    ]),
                ]);
            }

            $locked->update([
                'status' => PartnerAnnouncementStatus::Approved,
                'decided_by' => $admin->id,
                'decided_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->notifyOwner($profile, $locked);
        });

        $this->startAnnouncement->handle($admin, $announcement);
    }

    public function reject(
        User $admin,
        PartnerAnnouncement $announcement,
        ?string $reason = null,
    ): void {
        $this->ensureAdmin($admin);

        DB::transaction(function () use ($admin, $announcement, $reason): void {
            [$profile, $locked] = $this->lock($announcement);
            $this->ensurePending($locked);
            $locked->update([
                'status' => PartnerAnnouncementStatus::Rejected,
                'decided_by' => $admin->id,
                'decided_at' => now(),
                'rejection_reason' => $reason,
                'expires_at' => now()->addYears(2),
            ]);

            $this->notifyOwner($profile, $locked);
        });
    }

    public function cancel(User $actor, PartnerAnnouncement $announcement): void
    {
        DB::transaction(function () use ($actor, $announcement): void {
            [$profile, $locked] = $this->lock($announcement);

            if (! $actor->hasRole(RoleName::Admin) && $profile->user_id !== $actor->id) {
                throw new AuthorizationException;
            }

            if (! in_array($locked->status, [
                PartnerAnnouncementStatus::Draft,
                PartnerAnnouncementStatus::PendingApproval,
                PartnerAnnouncementStatus::Approved,
            ], true)) {
                throw ValidationException::withMessages([
                    'decision' => __('administration.partner_announcements.errors.not_cancellable'),
                ]);
            }

            $locked->update([
                'status' => PartnerAnnouncementStatus::Cancelled,
                'decided_by' => $actor->hasRole(RoleName::Admin) ? $actor->id : null,
                'decided_at' => now(),
                'expires_at' => now()->addYears(2),
            ]);
        });
    }

    /** @return array{PartnerProfile, PartnerAnnouncement} */
    private function lock(PartnerAnnouncement $announcement): array
    {
        $profile = PartnerProfile::query()
            ->whereKey($announcement->partner_profile_id)
            ->lockForUpdate()
            ->firstOrFail();
        $locked = PartnerAnnouncement::query()
            ->whereKey($announcement->id)
            ->where('partner_profile_id', $profile->id)
            ->lockForUpdate()
            ->firstOrFail();

        return [$profile, $locked];
    }

    private function ensureAdmin(User $user): void
    {
        if (! $user->hasRole(RoleName::Admin)) {
            throw new AuthorizationException;
        }
    }

    private function ensurePending(PartnerAnnouncement $announcement): void
    {
        if ($announcement->status !== PartnerAnnouncementStatus::PendingApproval) {
            throw ValidationException::withMessages([
                'decision' => __('administration.partner_announcements.errors.not_pending'),
            ]);
        }
    }

    private function notifyOwner(PartnerProfile $profile, PartnerAnnouncement $announcement): void
    {
        $owner = $profile->user()->first();

        if ($owner !== null) {
            $owner->notify(new PartnerAnnouncementDecisionNotification($announcement));
        }
    }
}
