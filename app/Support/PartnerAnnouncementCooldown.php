<?php

namespace App\Support;

use App\Models\PartnerAnnouncement;
use App\Models\PartnerSetting;
use Carbon\CarbonImmutable;

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

        if ($latestStart === null) {
            return null;
        }

        $availableAt = CarbonImmutable::parse($latestStart)->addDays($cooldownDays);

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
