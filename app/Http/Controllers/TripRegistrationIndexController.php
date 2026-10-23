<?php

namespace App\Http\Controllers;

use App\Data\TripWorkspaceData;
use App\Models\Trip;
use App\Models\TripDepartureReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class TripRegistrationIndexController extends Controller
{
    public function __invoke(Request $request, Trip $trip, TripWorkspaceData $data): Response
    {
        $viewer = $request->user();
        Gate::forUser($viewer)->authorize('manage', $trip);

        return Inertia::render('Trips/Show', [
            'trip' => $data->detail($viewer, $trip), 'panel' => 'requests',
            'registrations' => $trip->registrations()->with('user.profile.avatar')->orderByDesc('id')->get()->map(fn ($registration) => [
                'id' => $registration->id, 'status' => $registration->status->value, 'reason' => $registration->reason,
                'member' => $registration->user === null ? null : $data->member($registration->user),
            ]),
            'departures' => $trip->departures()->with('user.profile')->orderByDesc('id')->get()->map(fn ($departure) => [
                'id' => $departure->id, 'display_name' => $departure->user?->profile?->display_name,
                'left_at' => $departure->left_at->toIso8601String(), 'reason' => $departure->reason,
                'can_report' => Gate::forUser($viewer)->allows('create', [TripDepartureReport::class, $departure]),
            ]),
        ]);
    }
}
