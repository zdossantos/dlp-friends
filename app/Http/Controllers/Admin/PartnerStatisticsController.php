<?php

namespace App\Http\Controllers\Admin;

use App\Data\PartnerAnnouncementStatisticsData;
use App\Enums\PartnerDeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\PartnerAnnouncement;
use App\Models\User;
use App\Support\PartnerAnnouncementCooldown;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

final class PartnerStatisticsController extends Controller
{
    public function __invoke(PartnerAnnouncementCooldown $cooldown): Response
    {
        return Inertia::render('Admin/Partners/Statistics', [
            'eligibleRecipientCount' => User::query()->eligibleForPartnerAnnouncements()->count(),
            'announcements' => PartnerAnnouncement::query()
                ->with(['metric', 'partnerProfile.publishedRevision'])
                ->withCount([
                    'deliveries as pending_count' => fn (Builder $query) => $query
                        ->where('status', PartnerDeliveryStatus::Pending),
                    'deliveries as failed_count' => fn (Builder $query) => $query
                        ->where('status', PartnerDeliveryStatus::Failed),
                    'deliveries as skipped_count' => fn (Builder $query) => $query
                        ->where('status', PartnerDeliveryStatus::Skipped),
                ])
                ->latest('id')
                ->get()
                ->map(fn (PartnerAnnouncement $announcement): array => [
                    ...PartnerAnnouncementStatisticsData::from($announcement, includeOperations: true),
                    'partner_name' => $this->partnerName($announcement),
                    'next_dispatch_at' => $cooldown->nextAvailableAt(
                        $announcement->partner_profile_id,
                        $announcement->id,
                    )?->toIso8601String(),
                ]),
        ]);
    }

    private function partnerName(PartnerAnnouncement $announcement): ?string
    {
        $revision = $announcement->partnerProfile->publishedRevision;

        return app()->getLocale() === 'en'
            ? $revision?->name_en
            : $revision?->name_fr;
    }
}
