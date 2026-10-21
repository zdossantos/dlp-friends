<?php

namespace App\Actions;

use App\Enums\EventChatSystemType;
use App\Enums\TripRegistrationStatus;
use App\Events\GroupChatAccessChanged;
use App\Models\Trip;
use App\Models\TripRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class DecideTripRegistration
{
    public function handle(User $organizer, TripRegistration $registration, bool $accept, ?string $reason): TripRegistration
    {
        return DB::transaction(function () use ($organizer, $registration, $accept, $reason): TripRegistration {
            $accounts = app(LockTripAccounts::class)->handle([$organizer->id, $registration->user_id]);
            $organizer = $accounts->get($organizer->id) ?? abort(403);
            $trip = Trip::query()->lockForUpdate()->findOrFail($registration->trip_id);
            $registration = TripRegistration::query()->lockForUpdate()->findOrFail($registration->id);
            Gate::forUser($organizer)->authorize('manage', $trip);
            if ($trip->cancelled_at !== null || ! $trip->departs_at->isFuture()) {
                throw ValidationException::withMessages(['registration' => __('trips.validation.closed')]);
            }
            $target = $accept ? TripRegistrationStatus::Accepted : TripRegistrationStatus::Refused;
            if ($registration->status === $target) {
                return $registration;
            }
            if ($registration->status !== TripRegistrationStatus::Pending) {
                throw ValidationException::withMessages(['registration' => __('trips.validation.pending')]);
            }
            if ($accept) {
                $member = $accounts->get($registration->user_id) ?? abort(403);
                // Recheck audience, blocks and account state against the locked records.
                Gate::forUser($member)->authorize('view', $trip);
                if ($trip->remainingPlaces() === 0) {
                    throw ValidationException::withMessages(['registration' => __('trips.validation.full')]);
                }
                Gate::forUser($member)->authorize('request', $trip);
            } else {
                Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:1000']], attributes: __('trips.attributes'))->validate();
            }
            $registration->update(['status' => $target, 'reason' => $accept ? null : $reason, 'decided_at' => now()]);
            if ($accept && $trip->first_accepted_at === null) {
                $trip->update(['first_accepted_at' => now()]);
            }

            if ($accept) {
                $announcement = app(AnnounceGroupChatMembership::class)->handle($registration, EventChatSystemType::Joined);
                GroupChatAccessChanged::dispatch($announcement->group_chat_id, $registration->user_id, 'granted');
            }

            return $registration->refresh();
        });
    }
}
