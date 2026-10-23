<?php

namespace App\Http\Controllers;

use App\Actions\RequestTripParticipation;
use App\Actions\WithdrawFromTrip;
use App\Http\Requests\RequestTripParticipationRequest;
use App\Http\Requests\WithdrawFromTripRequest;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;

class TripRegistrationController extends Controller
{
    public function store(RequestTripParticipationRequest $request, Trip $trip, RequestTripParticipation $action): RedirectResponse
    {
        $action->handle($request->user(), $trip);

        return to_route('trips.show', $trip);
    }

    public function destroy(WithdrawFromTripRequest $request, Trip $trip, WithdrawFromTrip $action): RedirectResponse
    {
        $action->handle($request->user(), $trip, $request->validated('reason'));

        return to_route('trips.show', $trip);
    }
}
