<?php

namespace App\Http\Controllers;

use App\Actions\RemoveEventParticipant;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class EventRegistrationRemovalController extends Controller
{
    public function __invoke(
        Request $request,
        EventRegistration $registration,
        RemoveEventParticipant $remove,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $remove->handle($user, $registration);

        return back();
    }
}
