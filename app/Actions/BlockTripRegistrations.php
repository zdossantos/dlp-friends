<?php

namespace App\Actions;

use App\Enums\TripRegistrationStatus;
use App\Events\GroupChatAccessChanged;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class BlockTripRegistrations
{
    public function requiresDepartureConfirmation(User $blocker, User $blocked): bool
    {
        return $this->commonParticipations($blocker, $blocked)->exists();
    }

    public function handle(User $blocker, User $blocked): void
    {
        $trips = Trip::query()->where(function (Builder $query) use ($blocker, $blocked): void {
            $query->where(fn (Builder $owned) => $owned->where('organizer_user_id', $blocker->id)
                ->whereHas('registrations', fn (Builder $registrations) => $registrations->where('user_id', $blocked->id)->whereIn('status', [TripRegistrationStatus::Pending, TripRegistrationStatus::Accepted])))
                ->orWhere(fn (Builder $owned) => $owned->where('organizer_user_id', $blocked->id)
                    ->whereHas('registrations', fn (Builder $registrations) => $registrations->where('user_id', $blocker->id)->whereIn('status', [TripRegistrationStatus::Pending, TripRegistrationStatus::Accepted])))
                ->orWhereIn('id', $this->commonParticipations($blocker, $blocked)->select('id'));
        })->orderBy('id')->lockForUpdate()->get();

        foreach ($trips as $trip) {
            $participantId = $trip->organizer_user_id === $blocker->id ? $blocked->id : $blocker->id;
            $registration = $trip->registrations()->where('user_id', $participantId)->lockForUpdate()->first();
            if ($registration === null || ! in_array($registration->status, [TripRegistrationStatus::Pending, TripRegistrationStatus::Accepted], true)) {
                continue;
            }
            $hadAccess = $registration->status === TripRegistrationStatus::Accepted;
            $registration->update(['status' => TripRegistrationStatus::Blocked, 'reason' => null, 'decided_at' => now()]);
            $chat = $trip->chat()->first();
            if ($hadAccess && $chat !== null) {
                GroupChatAccessChanged::dispatch($chat->id, $participantId, 'revoked');
            }
        }
    }

    /** @return Builder<Trip> */
    private function commonParticipations(User $blocker, User $blocked): Builder
    {
        return Trip::query()->whereHas('registrations', fn (Builder $registrations) => $registrations->where('user_id', $blocker->id)->where('status', TripRegistrationStatus::Accepted))
            ->whereHas('registrations', fn (Builder $registrations) => $registrations->where('user_id', $blocked->id)->where('status', TripRegistrationStatus::Accepted));
    }
}
