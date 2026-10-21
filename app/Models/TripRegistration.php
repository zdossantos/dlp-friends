<?php

namespace App\Models;

use App\Enums\TripRegistrationStatus;
use Database\Factories\TripRegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $trip_id
 * @property int|null $user_id
 * @property TripRegistrationStatus $status
 * @property string|null $reason
 * @property-read Trip $trip
 * @property-read User|null $user
 */
#[Fillable(['user_id', 'status', 'reason', 'decided_at'])]
class TripRegistration extends Model
{
    /** @use HasFactory<TripRegistrationFactory> */
    use HasFactory;

    /** @return BelongsTo<Trip, $this> */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => TripRegistrationStatus::class, 'decided_at' => 'immutable_datetime'];
    }
}
