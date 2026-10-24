<?php

namespace App\Data;

use App\Models\Trip;
use App\Models\User;
use App\Policies\TripPolicy;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
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
            ...GroupChatData::conversation($trip->chat, $viewer),
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

    /** @param Collection<int, Trip> $trips
     * @return Collection<int, array<string, mixed>>
     */
    public function summaries(User $viewer, Collection $trips): Collection
    {
        if ($trips->isEmpty()) {
            return collect();
        }
        $viewer = $viewer->fresh(['roles', 'profile.avatar']) ?? abort(403);
        (new EloquentCollection($trips->all()))->load([
            'organizer.profile.avatar',
            'registrations' => fn ($query) => $query->where('user_id', $viewer->id),
            'chat' => fn ($query) => $query->visibleTo($viewer),
        ]);
        $visible = Trip::query()->visibleTo($viewer)->whereKey($trips->pluck('id')->all())->pluck('id')->flip();
        $requestable = Trip::query()->requestableBy($viewer)->whereKey($trips->pluck('id')->all())->pluck('id')->flip();

        return $trips->map(fn (Trip $trip): array => $this->present($viewer, $trip, true, [
            'visible' => $visible->has($trip->id),
            'request' => $requestable->has($trip->id),
            'chat' => $trip->chat !== null,
        ]));
    }

    /** @return array<string, mixed> */
    public function summary(User $viewer, Trip $trip): array
    {
        return $this->present($viewer, $trip, true);
    }

    /** @return array<string, mixed> */
    public function detail(User $viewer, Trip $trip): array
    {
        return $this->present($viewer, $trip);
    }

    /** @param array{visible: bool, request: bool, chat: bool}|null $access
     * @return array<string, mixed>
     */
    private function present(User $viewer, Trip $trip, bool $summary = false, ?array $access = null): array
    {
        $own = $trip->relationLoaded('registrations')
            ? $trip->registrations->firstWhere('user_id', $viewer->id)
            : $trip->registrations()->where('user_id', $viewer->id)->first();
        $visible = $access['visible'] ?? Gate::forUser($viewer)->allows('view', $trip);
        $canRequest = $access['request'] ?? Gate::forUser($viewer)->allows('request', $trip);
        if (! $visible || ($own !== null && ! in_array($own->status->value, ['pending', 'accepted'], true) && $trip->organizer_user_id !== $viewer->id)) {
            abort_unless($own !== null && app(TripPolicy::class)->create($viewer), 403);

            $data = ['id' => $trip->id, 'registration' => ['id' => $own->id, 'status' => $own->status->value, 'reason' => $own->reason], 'can_manage' => false, 'can_request' => $canRequest];
            if (in_array($own->status->value, ['pending', 'accepted'], true) && $trip->cancelled_at !== null) {
                $data['cancelled_at'] = $trip->cancelled_at->toIso8601String();
                $data['cancellation_reason'] = $trip->cancellation_reason;
            }

            return $data;
        }
        $canManage = Gate::forUser($viewer)->allows('manage', $trip);
        $data = [
            'id' => $trip->id, 'transport' => $trip->transport->value, 'direction' => $trip->direction->value,
            'general_departure' => $trip->general_departure, 'destination' => $trip->destination,
            'departs_at' => $trip->departs_at->toIso8601String(), 'audience' => $trip->audience->value,
            'arrives_at' => $trip->arrives_at?->toIso8601String(),
            'estimated_duration_minutes' => $trip->arrives_at === null ? null : (int) $trip->departs_at->diffInMinutes($trip->arrives_at),
            'remaining_places' => $trip->remainingPlaces(), 'car_seats' => $trip->car_seats,
            'cancelled_at' => $trip->cancelled_at?->toIso8601String(), 'first_accepted_at' => $trip->first_accepted_at?->toIso8601String(),
            'organizer' => $trip->organizer === null ? null : $this->member($trip->organizer),
            'registration' => $own === null ? null : ['id' => $own->id, 'status' => $own->status->value, 'reason' => $own->reason],
            'can_manage' => $canManage,
            'can_request' => $canRequest,
            'can_edit' => $canManage && $trip->cancelled_at === null && $trip->departs_at->isFuture(),
        ];
        if ($trip->organizer_user_id === $viewer->id || $own?->status->value === 'accepted') {
            if (! $summary) {
                $data['meeting_point'] = $trip->meeting_point;
            }
            if ($trip->chat !== null && ($access['chat'] ?? Gate::forUser($viewer)->allows('view', $trip->chat))) {
                $data['chat_href'] = route('trips.chat.show', $trip, absolute: false);
            }
            $data['cancellation_reason'] = $trip->cancellation_reason;
        }

        return $data;
    }
}
