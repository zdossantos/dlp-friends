<?php

namespace App\Http\Controllers;

use App\Actions\CreateTrip;
use App\Actions\UpdateTrip;
use App\Data\TripWorkspaceData;
use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\TripIndexRequest;
use App\Http\Requests\UpdateTripRequest;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TripController extends Controller
{
    public function index(TripIndexRequest $request, TripWorkspaceData $data): Response
    {
        $viewer = $request->user();
        $filters = $request->validated();

        $trips = Trip::query()->withAcceptedRegistrationCount()->visibleTo($viewer)->searching($filters['search'] ?? null)->when($filters['transport'] ?? null, fn ($query, $transport) => $query->where('transport', $transport))->whereNull('cancelled_at')->where('departs_at', '>', now())->orderBy('departs_at')->orderBy('id')->paginate(20)->withQueryString();
        $summaries = $data->summaries($viewer, $trips->getCollection())->keyBy('id')->all();
        $trips->through(fn (Trip $trip): array => $summaries[$trip->id]);

        return Inertia::render('Trips/Index', ['filters' => $filters, 'trips' => $trips]);
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
