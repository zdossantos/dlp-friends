<?php

namespace App\Http\Controllers;

use App\Data\EventDetailData;
use App\Data\EventWorkspaceData;
use App\Http\Requests\EventFilterRequest;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;

final class EventRegistrationIndexController extends Controller
{
    public function __construct(private readonly EventWorkspaceData $workspace) {}

    public function __invoke(EventFilterRequest $request, Event $event): Response
    {
        /** @var User $viewer */
        $viewer = $request->user();
        Gate::forUser($viewer)->authorize('update', $event);

        return $this->workspace->render($viewer, $this->workspace->context($request), [
            'kind' => 'registrations',
            'event' => EventDetailData::from(
                $event->load('organizer.profile.avatar'),
                $viewer,
            ),
        ], $request->filters());
    }
}
