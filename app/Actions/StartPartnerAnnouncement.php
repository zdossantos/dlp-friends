<?php

namespace App\Actions;

use App\Enums\PartnerAnnouncementStatus;
use App\Enums\RoleName;
use App\Jobs\PreparePartnerAnnouncementAudience;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerAnnouncementMetric;
use App\Models\PartnerProfile;
use App\Models\User;
use App\Rules\SafeHttpsUrl;
use App\Support\PartnerAnnouncementCooldown;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StartPartnerAnnouncement
{
    public function __construct(private readonly PartnerAnnouncementCooldown $cooldown) {}

    public function handle(User $admin, PartnerAnnouncement $announcement): void
    {
        if (! $admin->hasRole(RoleName::Admin)) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($announcement): void {
            $this->cooldown->acquireGlobalLock();

            $profile = PartnerProfile::query()
                ->whereKey($announcement->partner_profile_id)
                ->lockForUpdate()
                ->firstOrFail();
            $locked = PartnerAnnouncement::query()
                ->whereKey($announcement->id)
                ->where('partner_profile_id', $profile->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== PartnerAnnouncementStatus::Approved) {
                throw ValidationException::withMessages([
                    'announcement' => __('notifications.admin.not_approved'),
                ]);
            }

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
                    'announcement' => __('administration.partner_announcements.errors.cooldown', [
                        'date' => $this->cooldown->formatted($availableAt),
                    ]),
                ]);
            }

            $locked->update([
                'status' => PartnerAnnouncementStatus::Sending,
                'run_uuid' => (string) Str::uuid(),
                'audience_prepared_at' => null,
                'sending_started_at' => now(),
                'sent_at' => null,
            ]);
            PartnerAnnouncementMetric::query()->firstOrCreate([
                'partner_announcement_id' => $locked->id,
            ]);

            $announcementId = $locked->id;
            DB::afterCommit(static fn () => PreparePartnerAnnouncementAudience::dispatch($announcementId));
        });
    }
}
