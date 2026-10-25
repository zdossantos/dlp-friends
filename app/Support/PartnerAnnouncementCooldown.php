<?php

namespace App\Support;

use App\Models\PartnerAnnouncement;
use App\Models\PartnerSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PartnerAnnouncementCooldown
{
    public function acquireGlobalLock(): void
    {
        PartnerSetting::query()
            ->whereKey(1)
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function nextAvailableAt(
        int $profileId,
        ?int $exceptAnnouncementId = null,
        bool $lockForUpdate = false,
    ): ?CarbonImmutable {
        $settingQuery = PartnerSetting::query();

        if ($lockForUpdate) {
            $settingQuery->lockForUpdate();
        }

        $cooldownDays = $settingQuery->firstOrCreate(
            ['id' => 1],
            ['cooldown_days' => 30],
        )->cooldown_days;
        $announcementQuery = PartnerAnnouncement::query()
            ->where('partner_profile_id', $profileId)
            ->whereNotNull('sending_started_at');

        if ($exceptAnnouncementId !== null) {
            $announcementQuery->whereKeyNot($exceptAnnouncementId);
        }

        if ($lockForUpdate) {
            $announcementQuery->lockForUpdate();
        }

        $latestStart = $announcementQuery
            ->latest('sending_started_at')
            ->value('sending_started_at');

        return $this->availableAfter($latestStart, $cooldownDays);
    }

    /**
     * Read-only projection; mutations still use nextAvailableAt under their locks.
     *
     * @param  Collection<int, PartnerAnnouncement>  $announcements
     * @return array<int, CarbonImmutable|null>
     */
    public function forAnnouncements(Collection $announcements): array
    {
        if ($announcements->isEmpty()) {
            return [];
        }
        $days = PartnerSetting::current()->cooldown_days;
        $ranked = DB::table('partner_announcements')
            ->whereIn('partner_profile_id', $announcements->pluck('partner_profile_id')->unique())
            ->whereNotNull('sending_started_at')
            ->select('id', 'partner_profile_id', 'sending_started_at')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY partner_profile_id ORDER BY sending_started_at DESC, id DESC) as sending_rank');
        // Two starts suffice: only the current announcement must be excluded.
        $starts = DB::query()->fromSub($ranked, 'ranked')->where('sending_rank', '<=', 2)->orderBy('sending_rank')->get()->groupBy('partner_profile_id');
        $result = [];
        foreach ($announcements as $announcement) {
            $latest = $starts->get($announcement->partner_profile_id)?->first(fn ($start): bool => (int) $start->id !== $announcement->id);
            $result[$announcement->id] = $this->availableAfter($latest?->sending_started_at, $days);
        }

        return $result;
    }

    private function availableAfter(CarbonImmutable|string|null $latestStart, int $days): ?CarbonImmutable
    {
        if ($latestStart === null) {
            return null;
        }
        $availableAt = CarbonImmutable::parse($latestStart)->addDays($days);

        return $availableAt->isAfter(now()) ? $availableAt : null;
    }

    public function formatted(CarbonImmutable $availableAt): string
    {
        $locale = app()->getLocale();
        $separator = $locale === 'fr' ? '[à]' : '[at]';

        return $availableAt
            ->setTimezone('Europe/Paris')
            ->settings(['locale' => $locale])
            ->isoFormat("dddd D MMMM YYYY {$separator} HH:mm");
    }
}
