<?php

namespace App\Actions;

use App\Enums\TripAudience;
use App\Models\Trip;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CreateTrip
{
    /** @param array<string, mixed> $data */
    public function handle(User $organizer, array $data): Trip
    {
        return DB::transaction(function () use ($organizer, $data): Trip {
            $organizer = User::query()->lockForUpdate()->findOrFail($organizer->id);
            Gate::forUser($organizer)->authorize('create', Trip::class);
            if (($data['audience'] ?? 'matches') === TripAudience::Members->value && ! filter_var($data['confirm_members'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                throw ValidationException::withMessages(['confirm_members' => __('trips.validation.confirm_members')]);
            }
            unset($data['confirm_members']);
            $data['audience'] ??= TripAudience::Matches;
            $data['departs_at'] = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', (string) $data['departs_at'], 'Europe/Paris')->utc();

            return $organizer->organizedTrips()->create($data);
        });
    }
}
