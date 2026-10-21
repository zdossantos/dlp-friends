<?php

namespace App\Data;

use App\Models\Trip;
use App\Models\User;
use App\Policies\TripPolicy;
use Illuminate\Support\Facades\Gate;

final class TripWorkspaceData
{
    /** @return array<string, mixed> */
    public function summary(User $viewer, Trip $trip): array
    {
        $data = $this->detail($viewer, $trip);
        unset($data['meeting_point']);

        return $data;
    }

    /** @return array<string, mixed> */
    public function detail(User $viewer, Trip $trip): array
    {
        $own = $trip->registrations()->where('user_id', $viewer->id)->first();
        if (Gate::forUser($viewer)->denies('view', $trip) || ($own !== null && ! in_array($own->status->value, ['pending', 'accepted'], true) && $trip->organizer_user_id !== $viewer->id)) {
            abort_unless($own !== null && app(TripPolicy::class)->create($viewer), 403);

            return ['id' => $trip->id, 'registration' => ['id' => $own->id, 'status' => $own->status->value, 'reason' => $own->reason], 'can_manage' => false, 'can_request' => false];
        }
        $data = [
            'id' => $trip->id, 'transport' => $trip->transport->value, 'direction' => $trip->direction->value,
            'general_departure' => $trip->general_departure, 'destination' => $trip->destination,
            'departs_at' => $trip->departs_at->toIso8601String(), 'audience' => $trip->audience->value,
            'remaining_places' => $trip->remainingPlaces(), 'car_seats' => $trip->car_seats,
            'cancelled_at' => $trip->cancelled_at?->toIso8601String(), 'first_accepted_at' => $trip->first_accepted_at?->toIso8601String(),
            'organizer' => ['id' => $trip->organizer_user_id, 'display_name' => $trip->organizer?->profile?->display_name],
            'registration' => $own === null ? null : ['id' => $own->id, 'status' => $own->status->value, 'reason' => $own->reason],
            'can_manage' => Gate::forUser($viewer)->allows('manage', $trip),
            'can_request' => Gate::forUser($viewer)->allows('request', $trip),
            'can_edit' => Gate::forUser($viewer)->allows('manage', $trip) && $trip->cancelled_at === null && $trip->departs_at->isFuture(),
        ];
        if (Gate::forUser($viewer)->allows('viewPrivate', $trip)) {
            $data['meeting_point'] = $trip->meeting_point;
            $data['cancellation_reason'] = $trip->cancellation_reason;
        }

        return $data;
    }
}
