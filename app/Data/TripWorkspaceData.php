<?php

namespace App\Data;

use App\Models\GroupChatMessage;
use App\Models\Trip;
use App\Models\User;
use App\Policies\TripPolicy;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class TripWorkspaceData
{
    public function conversation(User $viewer, Trip $trip): Response
    {
        Gate::forUser($viewer)->authorize('view', $trip->chat);

        return Inertia::render('Conversations/Group', [
            'trip' => fn (): array => $this->detail($viewer, $trip),
            'chat' => fn (): array => GroupChatData::chat($trip->chat),
            'messages' => Inertia::scroll(function () use ($trip, $viewer) {
                $messages = $trip->chat->messages()
                    ->with('author.profile')
                    ->withCount('reactions')
                    ->orderByDesc('id')
                    ->simplePaginate(10, ['*'], 'messages')
                    ->through(fn (GroupChatMessage $message): array => GroupChatData::message($message, $viewer));
                $messages->setCollection($messages->getCollection()->reverse()->values());

                return $messages;
            }),
            'panel' => null,
            'closeHref' => route('trips.chat.show', $trip, absolute: false),
        ]);
    }

    /** @return array<string, mixed> */
    public function member(User $member): array
    {
        $avatar = $member->profile?->avatar;

        return ['id' => $member->id, 'display_name' => $member->profile?->display_name,
            'avatar' => $avatar === null ? null : ['id' => $avatar->id, 'name' => $avatar->name, 'image_url' => route('avatars.image', $avatar), 'primary_color' => $avatar->primary_color, 'secondary_color' => $avatar->secondary_color]];
    }

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

            $data = ['id' => $trip->id, 'registration' => ['id' => $own->id, 'status' => $own->status->value, 'reason' => $own->reason], 'can_manage' => false, 'can_request' => Gate::forUser($viewer)->allows('request', $trip)];
            if (in_array($own->status->value, ['pending', 'accepted'], true) && $trip->cancelled_at !== null) {
                $data['cancelled_at'] = $trip->cancelled_at->toIso8601String();
                $data['cancellation_reason'] = $trip->cancellation_reason;
            }

            return $data;
        }
        $data = [
            'id' => $trip->id, 'transport' => $trip->transport->value, 'direction' => $trip->direction->value,
            'general_departure' => $trip->general_departure, 'destination' => $trip->destination,
            'departs_at' => $trip->departs_at->toIso8601String(), 'audience' => $trip->audience->value,
            'remaining_places' => $trip->remainingPlaces(), 'car_seats' => $trip->car_seats,
            'cancelled_at' => $trip->cancelled_at?->toIso8601String(), 'first_accepted_at' => $trip->first_accepted_at?->toIso8601String(),
            'organizer' => $trip->organizer === null ? null : $this->member($trip->organizer),
            'registration' => $own === null ? null : ['id' => $own->id, 'status' => $own->status->value, 'reason' => $own->reason],
            'can_manage' => Gate::forUser($viewer)->allows('manage', $trip),
            'can_request' => Gate::forUser($viewer)->allows('request', $trip),
            'can_edit' => Gate::forUser($viewer)->allows('manage', $trip) && $trip->cancelled_at === null && $trip->departs_at->isFuture(),
        ];
        if (Gate::forUser($viewer)->allows('viewPrivate', $trip)) {
            $data['meeting_point'] = $trip->meeting_point;
            if ($trip->chat !== null && Gate::forUser($viewer)->allows('view', $trip->chat)) {
                $data['chat_href'] = route('trips.chat.show', $trip, absolute: false);
            }
            $data['cancellation_reason'] = $trip->cancellation_reason;
        }

        return $data;
    }
}
