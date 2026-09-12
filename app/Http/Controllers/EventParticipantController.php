<?php

namespace App\Http\Controllers;

use App\Data\EventDetailData;
use App\Data\EventWorkspaceData;
use App\Data\PublicMemberData;
use App\Enums\EventRegistrationStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class EventParticipantController extends Controller
{
    public function __construct(private readonly EventWorkspaceData $workspace) {}

    public function index(Request $request, Event $event): Response
    {
        $viewer = $this->viewer($request);
        Gate::forUser($viewer)->authorize('view', $event);
        Gate::forUser($viewer)->authorize('viewPrivateDetails', $event);

        return $this->workspace->render($viewer, $this->workspace->context($request), [
            'kind' => 'participants',
            'event' => $this->eventData($event, $viewer),
        ]);
    }

    public function show(Request $request, Event $event, User $member): Response|RedirectResponse
    {
        $viewer = $this->viewer($request);
        Gate::forUser($viewer)->authorize('view', $event);
        Gate::forUser($viewer)->authorize('viewPrivateDetails', $event);

        $isParticipant = $event->organizer_user_id === $member->id
            || $event->registrations()
                ->where('user_id', $member->id)
                ->where('status', EventRegistrationStatus::Accepted)
                ->exists();
        abort_unless($isParticipant, 404);

        $member->load(['profile.avatar', 'profile.interests', 'roles']);
        if (! $viewer->is($member)) {
            if ($member->profile === null || ! Gate::forUser($viewer)->allows('viewPublic', $member->profile)) {
                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => __('events.errors.participant_profile_unavailable'),
                ]);

                $parameters = ['event' => $event];
                if ($this->workspace->context($request) === 'mine') {
                    $parameters['origin'] = 'mine';
                }

                return redirect()->to(route('events.participants.index', $parameters, absolute: false));
            }
        }

        return $this->workspace->render($viewer, $this->workspace->context($request), [
            'kind' => 'participant-profile',
            'event' => $this->eventData($event, $viewer),
            'profile' => PublicMemberData::from($viewer, $member),
        ]);
    }

    /** @return array<string, mixed> */
    private function eventData(Event $event, User $viewer): array
    {
        return EventDetailData::from(
            $event->load('organizer.profile.avatar'),
            $viewer,
        );
    }

    private function viewer(Request $request): User
    {
        /** @var User $viewer */
        $viewer = $request->user();

        return $viewer;
    }
}
