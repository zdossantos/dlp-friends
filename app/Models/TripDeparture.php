<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TripDepartureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $trip_id
 * @property int $trip_registration_id
 * @property int|null $user_id
 * @property CarbonImmutable $scheduled_departure_at
 * @property CarbonImmutable $left_at
 * @property string $reason
 * @property-read Trip $trip
 */
#[Fillable(['trip_registration_id', 'user_id', 'scheduled_departure_at', 'left_at', 'reason'])]
class TripDeparture extends Model
{
    /** @use HasFactory<TripDepartureFactory> */
    use HasFactory;

    /** @return BelongsTo<Trip, $this> */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /** @return BelongsTo<TripRegistration, $this> */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(TripRegistration::class, 'trip_registration_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['scheduled_departure_at' => 'immutable_datetime', 'left_at' => 'immutable_datetime'];
    }
}
