<?php

namespace App\Http\Controllers;

use App\Actions\RemoveTripParticipant;
use App\Http\Requests\RemoveTripParticipantRequest;
use App\Models\TripRegistration;
use Illuminate\Http\RedirectResponse;

class TripRegistrationRemovalController extends Controller
{
    public function __invoke(RemoveTripParticipantRequest $request, TripRegistration $registration, RemoveTripParticipant $action): RedirectResponse
    {
        $action->handle($request->user(), $registration, $request->validated('reason'));

        return to_route('trips.show', $registration->trip_id);
    }
}
