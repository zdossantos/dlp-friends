<?php

namespace App\Actions;

use App\Enums\TripNotificationType;
use App\Enums\TripRegistrationStatus;
use App\Models\Trip;
use App\Models\TripRegistration;
use App\Models\User;
use App\Notifications\TripLifecycleNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class RequestTripParticipation
{
    public function handle(User $member, Trip $trip): TripRegistration
    {
        return DB::transaction(function () use ($member, $trip): TripRegistration {
            $accounts = app(LockTripAccounts::class)->handle([$member->id, $trip->organizer_user_id]);
            $member = $accounts->get($member->id) ?? abort(403);
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);
            $registration = $trip->registrations()->where('user_id', $member->id)->lockForUpdate()->first();
            if ($registration !== null && in_array($registration->status, [TripRegistrationStatus::Pending, TripRegistrationStatus::Accepted], true)) {
                Gate::forUser($member)->authorize('view', $trip);

                return $registration;
            }
            Gate::forUser($member)->authorize('request', $trip);
            if ($registration === null) {
                $registration = $trip->registrations()->create(['user_id' => $member->id, 'status' => TripRegistrationStatus::Pending]);
            } else {
                $registration->update(['status' => TripRegistrationStatus::Pending, 'reason' => null, 'decided_at' => null]);
            }
            $trip->organizer?->notify(new TripLifecycleNotification($trip->id, TripNotificationType::Requested, $registration->id));

            return $registration->refresh();
        });
    }
}
