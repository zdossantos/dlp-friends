<?php

namespace App\Http\Controllers\Partner;

use App\Data\PartnerAnnouncementStatisticsData;
use App\Http\Controllers\Controller;
use App\Models\PartnerAnnouncement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class StatisticsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', PartnerAnnouncement::class);

        /** @var User $partner */
        $partner = $request->user();

        return Inertia::render('Partner/Statistics/Index', [
            'announcements' => PartnerAnnouncement::query()
                ->ownedBy($partner)
                ->with('metric')
                ->latest('id')
                ->get()
                ->map(fn (PartnerAnnouncement $announcement): array => PartnerAnnouncementStatisticsData::from($announcement)),
        ]);
    }
}
