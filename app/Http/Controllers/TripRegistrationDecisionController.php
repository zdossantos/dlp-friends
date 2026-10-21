<?php

namespace App\Http\Controllers;

use App\Actions\DecideTripRegistration;
use App\Http\Requests\DecideTripRegistrationRequest;
use App\Models\TripRegistration;
use Illuminate\Http\RedirectResponse;

class TripRegistrationDecisionController extends Controller
{
    public function __invoke(DecideTripRegistrationRequest $request, TripRegistration $registration, DecideTripRegistration $action): RedirectResponse
    {
        $action->handle($request->user(), $registration, $request->boolean('accept'), $request->validated('reason'));

        return to_route('trips.show', $registration->trip_id);
    }
}
