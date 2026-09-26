<?php

namespace App\Http\Controllers\Partner;

use App\Actions\SubmitPartnerProfileRevision;
use App\Http\Controllers\Controller;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class ProfileSubmissionController extends Controller
{
    public function __invoke(
        Request $request,
        SubmitPartnerProfileRevision $submitRevision,
    ): RedirectResponse {
        /** @var User $partner */
        $partner = $request->user();
        $profile = $partner->partnerProfile;

        if ($profile instanceof PartnerProfile) {
            Gate::authorize('submit', $profile);
        } else {
            Gate::authorize('create', PartnerProfile::class);
        }

        $submitRevision->handle($partner);
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('partners.profile.submitted'),
        ]);

        return to_route('partner.profile.edit');
    }
}
