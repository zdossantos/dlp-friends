<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ApprovePartnerProfileRevision;
use App\Actions\RejectPartnerProfileRevision;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DecidePartnerProfileRequest;
use App\Models\PartnerProfileRevision;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class PartnerProfileDecisionController extends Controller
{
    public function __invoke(
        DecidePartnerProfileRequest $request,
        PartnerProfileRevision $revision,
        ApprovePartnerProfileRevision $approveRevision,
        RejectPartnerProfileRevision $rejectRevision,
    ): RedirectResponse {
        /** @var User $admin */
        $admin = $request->user();
        $decision = $request->decision();

        if ($decision === 'approve') {
            $approveRevision->handle($admin, $revision);
        } else {
            $rejectRevision->handle($admin, $revision, $request->rejectionReason());
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __($decision === 'approve'
                ? 'administration.partners.approved'
                : 'administration.partners.rejected'),
        ]);

        return to_route('admin.partner-profiles.index');
    }
}
