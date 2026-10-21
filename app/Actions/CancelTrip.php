<?php

namespace App\Actions;

use App\Events\GroupChatAccessChanged;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CancelTrip
{
    public function handle(User $organizer, Trip $trip, string $reason): void
    {
        DB::transaction(function () use ($organizer, $trip, $reason): void {
            $accounts = app(LockTripAccounts::class)->handle([$organizer->id]);
            $organizer = $accounts->get($organizer->id) ?? abort(403);
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);
            Gate::forUser($organizer)->authorize('manage', $trip);
            if ($trip->cancelled_at !== null) {
                return;
            }
            if (! $trip->departs_at->isFuture()) {
                throw ValidationException::withMessages(['trip' => __('trips.validation.closed')]);
            }
            Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:1000']], attributes: __('trips.attributes'))->validate();
            $trip->update(['cancelled_at' => now(), 'cancellation_reason' => $reason]);
            $chat = $trip->chat()->first();
            if ($chat !== null) {
                $recipients = $trip->registrations()->where('status', 'accepted')->pluck('user_id')->push($organizer->id)->filter()->unique();
                foreach ($recipients as $userId) {
                    GroupChatAccessChanged::dispatch($chat->id, $userId, 'read_only');
                }
            }
        });
    }
}
