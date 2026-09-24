<?php

namespace App\Http\Controllers\Partner;

use App\Actions\CreatePartnerAnnouncementDraftFromRejected;
use App\Http\Controllers\Controller;
use App\Models\PartnerAnnouncement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class AnnouncementRevisionController extends Controller
{
    public function __invoke(
        Request $request,
        PartnerAnnouncement $announcement,
        CreatePartnerAnnouncementDraftFromRejected $createDraft,
    ): RedirectResponse {
        Gate::authorize('revise', $announcement);
        /** @var User $partner */
        $partner = $request->user();
        $draft = $createDraft->handle($partner, $announcement);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('partners.announcements.revised'),
        ]);

        return to_route('partner.announcements.edit', $draft);
    }
}
