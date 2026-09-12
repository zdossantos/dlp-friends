<?php

namespace App\Http\Controllers;

use App\Data\EventDetailData;
use App\Data\EventWorkspaceData;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;

final class EventRegistrationIndexController extends Controller
{
    public function __construct(private readonly EventWorkspaceData $workspace) {}

    public function __invoke(Request $request, Event $event): Response
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
        ]);
    }
}
