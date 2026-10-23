<?php

namespace App\Http\Controllers;

use App\Actions\CancelTrip;
use App\Http\Requests\CancelTripRequest;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;

class TripCancellationController extends Controller
{
    public function __invoke(CancelTripRequest $request, Trip $trip, CancelTrip $action): RedirectResponse
    {
        $action->handle($request->user(), $trip, $request->validated('reason'));

        return to_route('trips.show', $trip);
    }
}
