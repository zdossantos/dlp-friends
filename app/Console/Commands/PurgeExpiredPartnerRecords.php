<?php

namespace App\Console\Commands;

use App\Enums\PartnerAnnouncementStatus;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerAnnouncementMetric;
use App\Models\PartnerProfileRevision;
use App\Models\RoleAudit;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class PurgeExpiredPartnerRecords extends Command
{
    protected $signature = 'partners:purge-expired-records';

    protected $description = 'Purge expired partner audit, moderation, and aggregate records';

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
                RoleAudit::query()->whereKey($audits->modelKeys())->delete();
            });

        PartnerProfileRevision::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $expiredAt)
            ->orderBy('id')
            ->chunkById(500, function ($revisions): void {
                DB::transaction(function () use ($revisions): void {
                    $paths = $revisions->pluck('image_path')->filter()->unique()->values();

                    PartnerProfileRevision::query()
                        ->whereKey($revisions->modelKeys())
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
                PartnerAnnouncementMetric::query()->whereKey($metrics->modelKeys())->delete();
            });

        PartnerAnnouncement::query()
            ->whereIn('status', $terminalStatuses)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $expiredAt)
            ->orderBy('id')
            ->chunkById(500, function ($announcements): void {
                PartnerAnnouncement::query()->whereKey($announcements->modelKeys())->delete();
            });

        return self::SUCCESS;
    }
}
