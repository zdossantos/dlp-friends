<?php

namespace App\Http\Controllers\Admin;

use App\Actions\StartPartnerAnnouncement;
use App\Http\Controllers\Controller;
use App\Models\PartnerAnnouncement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

final class PartnerAnnouncementDispatchController extends Controller
{
    public function __invoke(
        Request $request,
        PartnerAnnouncement $announcement,
        StartPartnerAnnouncement $start,
    ): RedirectResponse {
        $start->handle($request->user(), $announcement);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('notifications.admin.dispatch_started'),
        ]);

        return to_route('admin.partner-statistics.index')
            ->with('success', __('notifications.admin.dispatch_started'));
    }
}
