<?php

namespace App\Console\Commands;

use App\Enums\PartnerAnnouncementStatus;
use App\Enums\UserStatus;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerAnnouncementMetric;
use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use App\Models\PartnerSetting;
use App\Models\RoleAudit;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class PurgeExpiredPartnerRecords extends Command
{
    protected $signature = 'partners:purge-expired-records';

    protected $description = 'Purge expired partner audit, moderation, and aggregate records';

    /** @param array<int, int> $profileIds */
    private function lockOwners(array $profileIds): void
    {
        User::query()->whereKey(PartnerProfile::query()->whereKey($profileIds)->pluck('user_id')->filter())->orderBy('id')->lockForUpdate()->get();
    }

    public function handle(): int
    {
        $expiredAt = now();
        $terminalStatuses = [
            PartnerAnnouncementStatus::Sent,
            PartnerAnnouncementStatus::Rejected,
            PartnerAnnouncementStatus::Cancelled,
        ];

        RoleAudit::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $expiredAt)
            ->orderBy('id')
            ->chunkById(500, function ($audits): void {
                DB::transaction(function () use ($audits): void {
                    User::query()->whereKey($audits->flatMap(fn (RoleAudit $audit): array => [$audit->actor_user_id, $audit->target_user_id])->filter()->unique()->sort()->values())->orderBy('id')->lockForUpdate()->get();
                    RoleAudit::query()->whereKey($audits->modelKeys())->whereDoesntHave('actor', fn (Builder $q) => $q->where('status', UserStatus::Banned))->whereDoesntHave('target', fn (Builder $q) => $q->where('status', UserStatus::Banned))->delete();
                }, 3);
            });

        PartnerProfileRevision::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $expiredAt)
            ->whereNotIn(
                'id',
                PartnerProfile::query()
                    ->published()
                    ->select('published_revision_id'),
            )
            ->orderBy('id')
            ->chunkById(500, function ($revisions): void {
                DB::transaction(function () use ($revisions): void {
                    $this->lockOwners($revisions->map(fn (PartnerProfileRevision $revision): int => $revision->partner_profile_id)->all());
                    PartnerSetting::query()->whereKey(1)->lockForUpdate()->firstOrFail();
                    $publishedRevisionIds = PartnerProfile::query()
                        ->published()
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->pluck('published_revision_id');
                    $expiredRevisions = PartnerProfileRevision::query()
                        ->whereKey($revisions->modelKeys())
                        ->whereDoesntHave('partnerProfile.user', fn (Builder $q) => $q->where('status', UserStatus::Banned))
                        ->when(
                            $publishedRevisionIds->isNotEmpty(),
                            fn (Builder $query) => $query->whereNotIn('id', $publishedRevisionIds),
                        )
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();
                    $paths = $expiredRevisions->pluck('image_path')->filter()->unique()->values();

                    PartnerProfileRevision::query()
                        ->whereKey($expiredRevisions->modelKeys())
                        ->delete();

                    foreach ($paths as $path) {
                        if (PartnerProfileRevision::query()->where('image_path', $path)->exists()) {
                            continue;
                        }

                        DB::afterCommit(fn () => Storage::delete($path));
                    }
                });
            });

        PartnerAnnouncementMetric::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $expiredAt)
            ->whereHas(
                'announcement',
                fn (Builder $announcements) => $announcements->whereIn('status', $terminalStatuses),
            )
            ->orderBy('id')
            ->chunkById(500, function ($metrics): void {
                DB::transaction(function () use ($metrics): void {
                    $profiles = PartnerAnnouncement::query()->whereKey($metrics->pluck('partner_announcement_id'))->get()->map(fn (PartnerAnnouncement $announcement): int => $announcement->partner_profile_id);
                    $this->lockOwners($profiles->all());
                    PartnerAnnouncementMetric::query()->whereKey($metrics->modelKeys())->whereDoesntHave('announcement.partnerProfile.user', fn (Builder $q) => $q->where('status', UserStatus::Banned))->delete();
                }, 3);
            });

        PartnerAnnouncement::query()
            ->whereIn('status', $terminalStatuses)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $expiredAt)
            ->orderBy('id')
            ->chunkById(500, function ($announcements): void {
                DB::transaction(function () use ($announcements): void {
                    $this->lockOwners($announcements->map(fn (PartnerAnnouncement $announcement): int => $announcement->partner_profile_id)->all());
                    PartnerAnnouncement::query()->whereKey($announcements->modelKeys())->whereDoesntHave('partnerProfile.user', fn (Builder $q) => $q->where('status', UserStatus::Banned))->delete();
                }, 3);
            });

        return self::SUCCESS;
    }
}
