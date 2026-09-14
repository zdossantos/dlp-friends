<?php

namespace App\Http\Controllers;

use App\Actions\DecideEventRegistration;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class EventRegistrationDecisionController extends Controller
{
    public function __invoke(
        Request $request,
        EventRegistration $registration,
        DecideEventRegistration $decide,
    ): RedirectResponse {
        $validated = $request->validate(['accept' => ['required', 'boolean']]);
        /** @var User $user */
        $user = $request->user();
        $decide->handle($user, $registration, (bool) $validated['accept']);

        return back();
    }
}
