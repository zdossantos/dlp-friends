<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ApprovePartnerProfileRevision;
use App\Enums\PartnerRevisionStatus;
use App\Http\Controllers\Controller;
use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class PartnerProfileController extends Controller
{
    public function index(): Response
    {
        $pendingRevisions = PartnerProfileRevision::query()
            ->where('status', PartnerRevisionStatus::PendingApproval)
            ->with('partnerProfile')
            ->oldest('submitted_at')
            ->oldest('id')
            ->get()
            ->map(fn (PartnerProfileRevision $revision): array => $this->revisionData($revision));
        $publishedProfiles = PartnerProfile::query()
            ->published()
            ->with('publishedRevision')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(fn (PartnerProfile $profile): array => [
                'id' => $profile->id,
                'position' => $profile->position,
                'revision' => $this->revisionData($profile->publishedRevision),
            ]);

        return Inertia::render('Admin/Partners/Profiles', [
            'pendingRevisions' => $pendingRevisions,
            'publishedProfiles' => $publishedProfiles,
        ]);
    }

    public function destroy(
        PartnerProfile $partnerProfile,
        ApprovePartnerProfileRevision $approveRevision,
    ): RedirectResponse {
        $approveRevision->unpublish($partnerProfile);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('administration.partners.unpublished'),
        ]);

        return to_route('admin.partner-profiles.index');
    }

    /** @return array{id: int, profileId: int, nameFr: string, nameEn: string, descriptionFr: string, descriptionEn: string, imageUrl: string|null, status: string, submittedAt: string|null, rejectionReason: string|null} */
    private function revisionData(PartnerProfileRevision $revision): array
    {
        return [
            'id' => $revision->id,
            'profileId' => $revision->partner_profile_id,
            'nameFr' => $revision->name_fr,
            'nameEn' => $revision->name_en,
            'descriptionFr' => $revision->description_fr,
            'descriptionEn' => $revision->description_en,
            'imageUrl' => $revision->image_path === null
                ? null
                : route('partner.profile-revisions.image', $revision),
            'status' => $revision->status->value,
            'submittedAt' => $revision->submitted_at?->toIso8601String(),
            'rejectionReason' => $revision->rejection_reason,
        ];
    }
}
