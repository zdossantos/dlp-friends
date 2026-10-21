<?php

namespace App\Http\Controllers;

use App\Data\PublicMemberData;
use App\Data\TripWorkspaceData;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class TripParticipantController extends Controller
{
    public function index(Request $request, Trip $trip, TripWorkspaceData $data): Response
    {
        $viewer = $request->user();
        Gate::forUser($viewer)->authorize('viewPrivate', $trip);
        $participants = $trip->registrations()->where('status', 'accepted')->with('user.profile.avatar')->get()
            ->filter(fn ($registration) => $registration->user !== null)
            ->map(fn ($registration) => ['id' => $registration->id, 'member' => $this->member($registration->user)]);
        $participants->prepend(['id' => null, 'member' => $this->member($trip->organizer)]);

        return Inertia::render('Trips/Show', ['trip' => $data->detail($viewer, $trip), 'panel' => 'participants', 'participants' => $participants->values()]);
    }

    public function show(Request $request, Trip $trip, User $member, TripWorkspaceData $data): Response
    {
        $viewer = $request->user();
        Gate::forUser($viewer)->authorize('view', $trip);
        if ($trip->organizer_user_id !== $member->id) {
            Gate::forUser($viewer)->authorize('viewPrivate', $trip);
            abort_unless($trip->registrations()->where('user_id', $member->id)->whereIn('status', $viewer->can('manage', $trip) ? ['pending', 'accepted'] : ['accepted'])->exists(), 404);
        }
        abort_unless($member->profile !== null && ($viewer->is($member) || Gate::forUser($viewer)->allows('viewPublic', $member->profile)), 404);

        return Inertia::render('Trips/Show', ['trip' => $data->detail($viewer, $trip), 'panel' => 'profile', 'profile' => ['isBlocked' => false] + PublicMemberData::from($viewer, $member)]);
    }

    /** @return array<string, mixed> */
    private function member(User $member): array
    {
        $avatar = $member->profile?->avatar;

        return ['id' => $member->id, 'display_name' => $member->profile?->display_name,
            'avatar' => $avatar === null ? null : ['id' => $avatar->id, 'name' => $avatar->name, 'image_url' => route('avatars.image', $avatar), 'primary_color' => $avatar->primary_color, 'secondary_color' => $avatar->secondary_color]];
    }
}
