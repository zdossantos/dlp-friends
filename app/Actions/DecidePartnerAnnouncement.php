<?php

namespace App\Actions;

use App\Enums\PartnerAnnouncementStatus;
use App\Enums\RoleName;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerProfile;
use App\Models\PartnerSetting;
use App\Models\User;
use App\Rules\SafeHttpsUrl;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class DecidePartnerAnnouncement
{
    public function approve(User $admin, PartnerAnnouncement $announcement): void
    {
        $this->ensureAdmin($admin);

        DB::transaction(function () use ($admin, $announcement): void {
            [$profile, $locked] = $this->lock($announcement);
            $this->ensurePending($locked);

            Validator::make(
                ['destination_url' => $locked->destination_url],
                ['destination_url' => ['required', 'string', 'max:2048', new SafeHttpsUrl]],
            )->validate();

            $cooldownDays = PartnerSetting::query()->lockForUpdate()->firstOrCreate(
                ['id' => 1],
                ['cooldown_days' => 30],
            )->cooldown_days;
            $latestStart = $profile->announcements()
                ->whereKeyNot($locked->id)
                ->whereNotNull('sending_started_at')
                ->lockForUpdate()
                ->latest('sending_started_at')
                ->value('sending_started_at');

            if ($latestStart !== null
                && CarbonImmutable::parse($latestStart)->isAfter(now()->subDays($cooldownDays))) {
                throw ValidationException::withMessages([
                    'decision' => __('administration.partner_announcements.errors.cooldown'),
                ]);
            }

            $locked->update([
                'status' => PartnerAnnouncementStatus::Approved,
                'decided_by' => $admin->id,
                'decided_at' => now(),
                'rejection_reason' => null,
            ]);
        });
    }

    public function reject(
        User $admin,
        PartnerAnnouncement $announcement,
        ?string $reason = null,
    ): void {
        $this->ensureAdmin($admin);

        DB::transaction(function () use ($admin, $announcement, $reason): void {
            [, $locked] = $this->lock($announcement);
            $this->ensurePending($locked);
            $locked->update([
                'status' => PartnerAnnouncementStatus::Rejected,
                'decided_by' => $admin->id,
                'decided_at' => now(),
                'rejection_reason' => $reason,
            ]);
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
}
