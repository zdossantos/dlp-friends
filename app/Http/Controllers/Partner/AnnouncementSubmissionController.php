<?php

namespace App\Http\Controllers\Partner;

use App\Actions\SubmitPartnerAnnouncement;
use App\Http\Controllers\Controller;
use App\Models\PartnerAnnouncement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class AnnouncementSubmissionController extends Controller
{
    public function __invoke(
        Request $request,
        PartnerAnnouncement $announcement,
        SubmitPartnerAnnouncement $submitAnnouncement,
    ): RedirectResponse {
        Gate::authorize('submit', $announcement);
        /** @var User $partner */
        $partner = $request->user();
        $submitAnnouncement->handle($partner, $announcement);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('partners.announcements.submitted'),
        ]);

        return to_route('partner.announcements.index');
    }
}
