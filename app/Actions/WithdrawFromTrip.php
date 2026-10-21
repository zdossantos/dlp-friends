<?php

namespace App\Actions;

use App\Enums\EventChatSystemType;
use App\Enums\TripRegistrationStatus;
use App\Events\GroupChatAccessChanged;
use App\Models\Trip;
use App\Models\User;
use App\Policies\TripPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class WithdrawFromTrip
{
    public function handle(User $member, Trip $trip, ?string $reason): void
    {
        DB::transaction(function () use ($member, $trip, $reason): void {
            $accounts = app(LockTripAccounts::class)->handle([$member->id, $trip->organizer_user_id]);
            $member = $accounts->get($member->id) ?? abort(403);
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);
            throw_unless(app(TripPolicy::class)->create($member) && $trip->organizer_user_id !== $member->id, AuthorizationException::class);
            $registration = $trip->registrations()->where('user_id', $member->id)->lockForUpdate()->first();
            abort_unless($registration !== null, 403);
            if (! in_array($registration->status, [TripRegistrationStatus::Pending, TripRegistrationStatus::Accepted], true)) {
                return;
            }
            $wasAccepted = $registration->status === TripRegistrationStatus::Accepted;
            if ($wasAccepted) {
                Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:1000']], attributes: __('trips.attributes'))->validate();
                $trip->departures()->create(['trip_registration_id' => $registration->id, 'user_id' => $member->id, 'scheduled_departure_at' => $trip->departs_at, 'left_at' => now(), 'reason' => $reason]);
            }
            $registration->update(['status' => TripRegistrationStatus::Withdrawn, 'reason' => $reason, 'decided_at' => now()]);
            if ($wasAccepted) {
                $announcement = app(AnnounceGroupChatMembership::class)->handle($registration, EventChatSystemType::Left);
                GroupChatAccessChanged::dispatch($announcement->group_chat_id, $registration->user_id, 'revoked');
            }
        });
    }
}
