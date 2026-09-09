<?php

namespace App\Http\Controllers;

use App\Data\EventSummaryData;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyEventController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $events = Event::query()
            ->where(fn (Builder $query) => $query
                ->where('organizer_user_id', $user->id)
                ->orWhereHas('registrations', fn (Builder $registrations) => $registrations
                    ->where('user_id', $user->id)))
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (Event $event): array => EventSummaryData::from($event, $user));

        return Inertia::render('Events/Mine', ['events' => $events]);
    }
}
