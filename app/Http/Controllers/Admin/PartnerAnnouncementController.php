<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PartnerAnnouncementStatus;
use App\Http\Controllers\Controller;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerSetting;
use Inertia\Inertia;
use Inertia\Response;

final class PartnerAnnouncementController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Partners/Announcements', [
            'cooldownDays' => PartnerSetting::current()->cooldown_days,
            'announcements' => PartnerAnnouncement::query()
                ->where('status', PartnerAnnouncementStatus::PendingApproval)
                ->with('partnerProfile.publishedRevision')
                ->oldest('submitted_at')
                ->oldest('id')
                ->get()
                ->map(fn (PartnerAnnouncement $announcement): array => [
                    'id' => $announcement->id,
                    'partnerName' => $announcement->partnerProfile->publishedRevision?->name_fr,
                    'title' => $announcement->title,
                    'content' => $announcement->content,
                    'destinationUrl' => $announcement->destination_url,
                    'submittedAt' => $announcement->submitted_at?->toIso8601String(),
                ]),
        ]);
    }
}
