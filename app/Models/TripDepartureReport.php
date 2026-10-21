<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TripDepartureReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $trip_departure_id
 * @property int|null $reporter_user_id
 * @property int|null $target_user_id
 * @property int|null $decided_by_user_id
 * @property CarbonImmutable $scheduled_departure_at
 * @property CarbonImmutable $left_at
 * @property string $details
 * @property string|null $open_key
 * @property string|null $decision
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $created_at
 * @property-read User|null $reporter
 * @property-read User|null $target
 */
#[Fillable(['trip_departure_id', 'reporter_user_id', 'target_user_id', 'scheduled_departure_at', 'left_at', 'details', 'open_key', 'decision', 'decided_by_user_id', 'closed_at'])]
class TripDepartureReport extends Model
{
    /** @use HasFactory<TripDepartureReportFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['scheduled_departure_at' => 'immutable_datetime', 'left_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }
}
