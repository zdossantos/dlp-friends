<?php

namespace App\Actions;

use App\Enums\TripAudience;
use App\Enums\TripRegistrationStatus;
use App\Models\Trip;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class UpdateTrip
{
    /** @param array<string, mixed> $data */
    public function handle(User $organizer, Trip $trip, array $data): Trip
    {
        return DB::transaction(function () use ($organizer, $trip, $data): Trip {
            $organizer = User::query()->lockForUpdate()->findOrFail($organizer->id);
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);
            Gate::forUser($organizer)->authorize('manage', $trip);
            if ($trip->cancelled_at !== null || $trip->departs_at->isPast()) {
                throw ValidationException::withMessages(['trip' => __('trips.validation.closed')]);
            }
            if (($data['audience'] ?? $trip->audience->value) === TripAudience::Members->value && $trip->audience !== TripAudience::Members && ! filter_var($data['confirm_members'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                throw ValidationException::withMessages(['confirm_members' => __('trips.validation.confirm_members')]);
            }
            unset($data['confirm_members']);
            $data['departs_at'] = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', (string) $data['departs_at'], 'Europe/Paris')->utc();
            if ($trip->first_accepted_at !== null) {
                foreach (['transport', 'direction', 'general_departure', 'destination', 'departs_at'] as $field) {
                    $original = $trip->getAttribute($field);
                    $original = $original instanceof \BackedEnum ? $original->value : $original;
                    if (($data[$field] ?? $original) != $original) {
                        throw ValidationException::withMessages([$field => __('trips.validation.locked')]);
                    }
                }
            }
            if ($trip->transport->value === 'car' && ($data['car_seats'] ?? 0) < $trip->registrations()->where('status', TripRegistrationStatus::Accepted)->count()) {
                throw ValidationException::withMessages(['car_seats' => __('trips.validation.capacity')]);
            }
            $trip->update($data);

            return $trip;
        });
    }
}
