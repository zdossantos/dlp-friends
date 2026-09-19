<?php

namespace App\Http\Controllers\Admin;

use App\Actions\DecidePartnerAnnouncement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DecidePartnerAnnouncementRequest;
use App\Models\PartnerAnnouncement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class PartnerAnnouncementDecisionController extends Controller
{
    public function __invoke(
        DecidePartnerAnnouncementRequest $request,
        PartnerAnnouncement $announcement,
        DecidePartnerAnnouncement $decideAnnouncement,
    ): RedirectResponse {
        /** @var User $admin */
        $admin = $request->user();
        $decision = $request->decision();

        if ($decision === 'approve') {
            $decideAnnouncement->approve($admin, $announcement);
            $message = __('administration.partner_announcements.approved');
        } elseif ($decision === 'reject') {
            $decideAnnouncement->reject(
                $admin,
                $announcement,
                $request->rejectionReason(),
            );
            $message = __('administration.partner_announcements.rejected');
        } else {
            $decideAnnouncement->cancel($admin, $announcement);
            $message = __('administration.partner_announcements.cancelled');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $message,
        ]);

        return to_route('admin.partner-announcements.index');
    }
}
