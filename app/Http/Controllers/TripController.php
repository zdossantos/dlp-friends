<?php

namespace App\Http\Controllers;

use App\Actions\CreateTrip;
use App\Actions\UpdateTrip;
use App\Data\TripWorkspaceData;
use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\UpdateTripRequest;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TripController extends Controller
{
    public function index(Request $request, TripWorkspaceData $data): Response
    {
        $viewer = $request->user();

        return Inertia::render('Trips/Index', ['trips' => Trip::query()->visibleTo($viewer)->whereNull('cancelled_at')->where('departs_at', '>', now())->orderBy('departs_at')->paginate(20)->through(fn (Trip $trip) => $data->summary($viewer, $trip))]);
    }

    public function store(StoreTripRequest $request, CreateTrip $create): RedirectResponse
    {
        $trip = $create->handle($request->user(), $request->validated());

        return to_route('trips.show', $trip);
    }

    public function show(Request $request, Trip $trip, TripWorkspaceData $data): Response
    {
        return Inertia::render('Trips/Show', ['trip' => $data->detail($request->user(), $trip)]);
    }

    public function update(UpdateTripRequest $request, Trip $trip, UpdateTrip $update): RedirectResponse
    {
        $update->handle($request->user(), $trip, $request->validated());

        return to_route('trips.show', $trip);
    }
}
