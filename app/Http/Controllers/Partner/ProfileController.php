<?php

namespace App\Http\Controllers\Partner;

use App\Actions\SavePartnerProfileDraft;
use App\Enums\PartnerRevisionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\SavePartnerProfileRequest;
use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $partner = $this->partner($request);
        $profile = $partner->partnerProfile;

        if ($profile === null) {
            Gate::authorize('create', PartnerProfile::class);
        } else {
            Gate::authorize('view', $profile);
        }

        $published = $profile?->is_published === true
            ? $profile->publishedRevision
            : null;
        $draft = $profile?->revisions()
            ->where('status', PartnerRevisionStatus::Draft)
            ->first();
        $latestSubmission = $profile?->revisions()
            ->where('status', '!=', PartnerRevisionStatus::Draft)
            ->latest('id')
            ->first();

        return Inertia::render('Partner/Profile/Edit', [
            'publishedRevision' => $this->revisionData($published),
            'draft' => $this->revisionData($draft),
            'latestSubmission' => $this->revisionData($latestSubmission),
        ]);
    }

    public function update(
        SavePartnerProfileRequest $request,
        SavePartnerProfileDraft $saveDraft,
    ): RedirectResponse {
        $uploadedImage = $request->file('image');
        $saveDraft->handle(
            $this->partner($request),
            $request->profileData(),
            $uploadedImage instanceof UploadedFile ? $uploadedImage : null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('partners.profile.saved'),
        ]);

        return to_route('partner.profile.edit');
    }

    /** @return array{id: int, nameFr: string, nameEn: string, descriptionFr: string, descriptionEn: string, imageUrl: string|null, status: string, submittedAt: string|null, decidedAt: string|null, rejectionReason: string|null}|null */
    private function revisionData(?PartnerProfileRevision $revision): ?array
    {
        if ($revision === null) {
            return null;
        }

        return [
            'id' => $revision->id,
            'nameFr' => $revision->name_fr,
            'nameEn' => $revision->name_en,
            'descriptionFr' => $revision->description_fr,
            'descriptionEn' => $revision->description_en,
            'imageUrl' => $revision->image_path === null
                ? null
                : route('partner.profile-revisions.image', $revision),
            'status' => $revision->status->value,
            'submittedAt' => $revision->submitted_at?->toIso8601String(),
            'decidedAt' => $revision->decided_at?->toIso8601String(),
            'rejectionReason' => $revision->rejection_reason,
        ];
    }

    private function partner(Request $request): User
    {
        /** @var User $partner */
        $partner = $request->user();

        return $partner;
    }
}
