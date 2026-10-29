<?php

namespace App\Http\Controllers;

use App\Actions\CreateEvent;
use App\Actions\UpdateEvent;
use App\Data\EventDetailData;
use App\Data\EventWorkspaceData;
use App\Http\Requests\EventFilterRequest;
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

    public function index(EventFilterRequest $request): Response
    {
        $user = $this->user($request);

        return $this->workspace->render($user, 'discover', null, $request->filters());
    }

    public function create(EventFilterRequest $request): Response
    {
        return $this->workspace->render(
            $this->user($request),
            $this->workspace->context($request),
            ['kind' => 'create'],
            $request->filters(),
        );
    }

    public function store(StoreEventRequest $request, CreateEvent $createEvent, EventFilterRequest $filters): RedirectResponse
    {
        $event = $createEvent->handle($this->user($request), $request->validated());

        return redirect()->to($this->workspace->detailUrl(
            $event,
            $this->workspace->context($request),
            $filters->filters(),
        ));
    }

    public function show(EventFilterRequest $request, Event $event): Response|RedirectResponse
    {
        $user = $this->user($request);
        if (Gate::forUser($user)->denies('view', $event)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('events.errors.event_unavailable'),
            ]);

            return redirect()->to($this->workspace->context($request) === 'mine'
                ? route('events.mine', $request->filters(), absolute: false)
                : route('events.index', $request->filters(), absolute: false));
        }

        $event->load('organizer.profile');

        return $this->workspace->render($user, $this->workspace->context($request), [
            'kind' => 'detail',
            'event' => EventDetailData::from($event, $user),
        ], $request->filters());
    }

    public function edit(EventFilterRequest $request, Event $event): Response
    {
        Gate::authorize('update', $event);

        $user = $this->user($request);

        return $this->workspace->render($user, $this->workspace->context($request), [
            'kind' => 'edit',
            'event' => EventDetailData::from($event->load('organizer.profile'), $user),
        ], $request->filters());
    }

    public function update(UpdateEventRequest $request, Event $event, UpdateEvent $updateEvent, EventFilterRequest $filters): RedirectResponse
    {
        $updateEvent->handle($this->user($request), $event, $request->validated());

        return redirect()->to($this->workspace->detailUrl(
            $event,
            $this->workspace->context($request),
            $filters->filters(),
        ));
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
