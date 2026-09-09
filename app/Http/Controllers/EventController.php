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
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(private readonly EventWorkspaceData $workspace) {}

    public function index(Request $request): Response
    {
        $user = $this->user($request);

        return $this->workspace->render($user, 'discover', null);
    }

    public function create(Request $request): Response
    {
        return $this->workspace->render(
            $this->user($request),
            $this->workspace->context($request),
            ['kind' => 'create'],
        );
    }

    public function store(StoreEventRequest $request, CreateEvent $createEvent): RedirectResponse
    {
        $event = $createEvent->handle($this->user($request), $request->validated());

        return redirect()->to($this->workspace->detailUrl(
            $event,
            $this->workspace->context($request),
        ));
    }

    public function show(Request $request, Event $event): Response
    {
        Gate::authorize('view', $event);
        $user = $this->user($request);
        $event->load('organizer.profile');

        return $this->workspace->render($user, $this->workspace->context($request), [
            'kind' => 'detail',
            'event' => EventDetailData::from($event, $user),
        ]);
    }

    public function edit(Request $request, Event $event): Response
    {
        Gate::authorize('update', $event);

        $user = $this->user($request);

        return $this->workspace->render($user, $this->workspace->context($request), [
            'kind' => 'edit',
            'event' => EventDetailData::from($event->load('organizer.profile'), $user),
        ]);
    }

    public function update(UpdateEventRequest $request, Event $event, UpdateEvent $updateEvent): RedirectResponse
    {
        $updateEvent->handle($this->user($request), $event, $request->validated());

        return redirect()->to($this->workspace->detailUrl(
            $event,
            $this->workspace->context($request),
        ));
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
