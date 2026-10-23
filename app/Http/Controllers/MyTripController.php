<?php

namespace App\Http\Controllers;

use App\Data\TripWorkspaceData;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyTripController extends Controller
{
    public function __invoke(Request $request, TripWorkspaceData $data): Response
    {
        $viewer = $request->user();
        $trips = Trip::query()->where(fn (Builder $query) => $query->where('organizer_user_id', $viewer->id)->orWhereHas('registrations', fn (Builder $registrations) => $registrations->where('user_id', $viewer->id)))
            ->orderByDesc('departs_at')->paginate(20)->through(fn (Trip $trip) => $data->summary($viewer, $trip));

        return Inertia::render('Trips/Mine', ['trips' => $trips]);
    }
}
