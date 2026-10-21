<?php

namespace App\Actions;

use App\Enums\EventChatSystemType;
use App\Enums\TripNotificationType;
use App\Enums\TripRegistrationStatus;
use App\Events\GroupChatAccessChanged;
use App\Models\Trip;
use App\Models\TripRegistration;
use App\Models\User;
use App\Notifications\TripLifecycleNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class RemoveTripParticipant
{
    public function handle(User $organizer, TripRegistration $registration, string $reason): void
    {
        DB::transaction(function () use ($organizer, $registration, $reason): void {
            $accounts = app(LockTripAccounts::class)->handle([$organizer->id, $registration->user_id]);
            $organizer = $accounts->get($organizer->id) ?? abort(403);
            $trip = Trip::query()->lockForUpdate()->findOrFail($registration->trip_id);
            $registration = TripRegistration::query()->lockForUpdate()->findOrFail($registration->id);
            Gate::forUser($organizer)->authorize('manage', $trip);
            if ($registration->status === TripRegistrationStatus::Removed) {
                return;
            }
            if ($trip->cancelled_at !== null || ! $trip->departs_at->isFuture() || $registration->status !== TripRegistrationStatus::Accepted) {
                throw ValidationException::withMessages(['registration' => __('trips.validation.closed')]);
            }
            Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:1000']], attributes: __('trips.attributes'))->validate();
            $registration->update(['status' => TripRegistrationStatus::Removed, 'reason' => $reason, 'decided_at' => now()]);
            $announcement = app(AnnounceGroupChatMembership::class)->handle($registration, EventChatSystemType::Removed);
            GroupChatAccessChanged::dispatch($announcement->group_chat_id, $registration->user_id, 'revoked');
            $registration->user?->notify(new TripLifecycleNotification($trip->id, TripNotificationType::Removed, $registration->id));
        });
    }
}
