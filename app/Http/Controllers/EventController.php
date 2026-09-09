<?php

namespace App\Http\Controllers;

use App\Actions\CreateEvent;
use App\Actions\UpdateEvent;
use App\Data\EventDetailData;
use App\Data\EventWorkspaceData;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(private readonly EventWorkspaceData $workspace) {}

    public function index(Request $request): Response
    {
        $user = $this->user($request);

        return Inertia::render('Events/Index', ['events' => $this->workspace->discovery($user)]);
    }

    public function create(): Response
    {
        return Inertia::render('Events/Create');
    }

    public function store(StoreEventRequest $request, CreateEvent $createEvent): RedirectResponse
    {
        $event = $createEvent->handle($this->user($request), $request->validated());

        return to_route('events.show', $event);
    }

    public function show(Request $request, Event $event): Response
    {
        Gate::authorize('view', $event);
        $user = $this->user($request);
        $event->load('organizer.profile');

        return Inertia::render('Events/Show', [
            'event' => EventDetailData::from($event, $user),
        ]);
    }

    public function edit(Request $request, Event $event): Response
    {
        Gate::authorize('update', $event);

        return Inertia::render('Events/Edit', [
            'event' => EventDetailData::from($event->load('organizer.profile'), $this->user($request)),
        ]);
    }

    public function update(UpdateEventRequest $request, Event $event, UpdateEvent $updateEvent): RedirectResponse
    {
        $updateEvent->handle($this->user($request), $event, $request->validated());

        return to_route('events.show', $event);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
