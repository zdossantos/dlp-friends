<?php

namespace App\Http\Controllers;

use App\Actions\CreateTrip;
use App\Actions\UpdateTrip;
use App\Data\TripWorkspaceData;
use App\Enums\TripTransport;
use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\UpdateTripRequest;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TripController extends Controller
{
    public function index(Request $request, TripWorkspaceData $data): Response
    {
        $viewer = $request->user();
        $filters = $request->validate(['transport' => ['nullable', Rule::enum(TripTransport::class)]]);

        return Inertia::render('Trips/Index', ['filters' => $filters, 'trips' => Trip::query()->visibleTo($viewer)->when($filters['transport'] ?? null, fn ($query, $transport) => $query->where('transport', $transport))->whereNull('cancelled_at')->where('departs_at', '>', now())->orderBy('departs_at')->paginate(20)->withQueryString()->through(fn (Trip $trip) => $data->summary($viewer, $trip))]);
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
