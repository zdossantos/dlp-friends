<?php

namespace App\Http\Controllers\Partner;

use App\Actions\DecidePartnerAnnouncement;
use App\Actions\DeletePartnerAnnouncementDraft;
use App\Actions\SavePartnerAnnouncement;
use App\Enums\PartnerAnnouncementStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\SavePartnerAnnouncementRequest;
use App\Models\PartnerAnnouncement;
use App\Models\User;
use App\Support\PartnerAnnouncementCooldown;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class AnnouncementController extends Controller
{
    public function index(Request $request, PartnerAnnouncementCooldown $cooldown): Response
    {
        Gate::authorize('viewAny', PartnerAnnouncement::class);
        $partner = $this->partner($request);
        $profile = $partner->partnerProfile;
        $nextSubmissionAt = $profile === null
            ? null
            : $cooldown->nextAvailableAt($profile->id);

        return Inertia::render('Partner/Announcements/Index', [
            'canCreate' => $partner->can('create', PartnerAnnouncement::class),
            'announcements' => PartnerAnnouncement::query()
                ->ownedBy($partner)
                ->latest('id')
                ->get()
                ->map(fn (PartnerAnnouncement $announcement): array => $this->announcementData(
                    $announcement,
                    $nextSubmissionAt?->toIso8601String(),
                )),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', PartnerAnnouncement::class);

        return Inertia::render('Partner/Announcements/Edit', [
            'announcement' => null,
        ]);
    }

    public function store(
        SavePartnerAnnouncementRequest $request,
        SavePartnerAnnouncement $saveAnnouncement,
    ): RedirectResponse {
        $saveAnnouncement->handle($this->partner($request), $request->announcementData());

        return $this->saved();
    }

    public function edit(Request $request, PartnerAnnouncement $announcement): Response
    {
        Gate::authorize('update', $announcement);

        if ($announcement->status !== PartnerAnnouncementStatus::Draft) {
            throw ValidationException::withMessages([
                'announcement' => __('partners.announcements.errors.not_draft'),
            ]);
        }

        return Inertia::render('Partner/Announcements/Edit', [
            'announcement' => $this->announcementData($announcement),
        ]);
    }

    public function update(
        SavePartnerAnnouncementRequest $request,
        PartnerAnnouncement $announcement,
        SavePartnerAnnouncement $saveAnnouncement,
    ): RedirectResponse {
        $saveAnnouncement->handle(
            $this->partner($request),
            $request->announcementData(),
            $announcement,
        );

        return $this->saved();
    }

    public function destroy(
        Request $request,
        PartnerAnnouncement $announcement,
        DeletePartnerAnnouncementDraft $deleteAnnouncement,
    ): RedirectResponse {
        Gate::authorize('delete', $announcement);
        $deleteAnnouncement->handle($this->partner($request), $announcement);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('partners.announcements.deleted'),
        ]);

        return to_route('partner.announcements.index');
    }

    public function cancel(
        Request $request,
        PartnerAnnouncement $announcement,
        DecidePartnerAnnouncement $decideAnnouncement,
    ): RedirectResponse {
        Gate::authorize('cancel', $announcement);
        $decideAnnouncement->cancel($this->partner($request), $announcement);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('partners.announcements.cancelled'),
        ]);

        return to_route('partner.announcements.index');
    }

    /** @return array{id: int, title: string, content: string, destinationUrl: string, status: string, submittedAt: string|null, decidedAt: string|null, rejectionReason: string|null, canEdit: bool, canSubmit: bool, nextSubmissionAt: string|null, canCancel: bool, canRevise: bool} */
    private function announcementData(
        PartnerAnnouncement $announcement,
        ?string $nextSubmissionAt = null,
    ): array {
        return [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'content' => $announcement->content,
            'destinationUrl' => $announcement->destination_url,
            'status' => $announcement->status->value,
            'submittedAt' => $announcement->submitted_at?->toIso8601String(),
            'decidedAt' => $announcement->decided_at?->toIso8601String(),
            'rejectionReason' => $announcement->rejection_reason,
            'canEdit' => $announcement->status === PartnerAnnouncementStatus::Draft,
            'canSubmit' => $announcement->status === PartnerAnnouncementStatus::Draft
                && $nextSubmissionAt === null,
            'nextSubmissionAt' => $announcement->status === PartnerAnnouncementStatus::Draft
                ? $nextSubmissionAt
                : null,
            'canCancel' => in_array($announcement->status, [
                PartnerAnnouncementStatus::PendingApproval,
                PartnerAnnouncementStatus::Approved,
            ], true),
            'canRevise' => $announcement->status === PartnerAnnouncementStatus::Rejected,
        ];
    }

    private function saved(): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('partners.announcements.saved'),
        ]);

        return to_route('partner.announcements.index');
    }

    private function partner(Request $request): User
    {
        /** @var User $partner */
        $partner = $request->user();

        return $partner;
    }
}
