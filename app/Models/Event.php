<?php

namespace App\Models;

use App\Enums\EventRegistrationMode;
use App\Enums\EventRegistrationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $organizer_user_id
 * @property string $title
 * @property string $description
 * @property string $general_location
 * @property string $detailed_location
 * @property CarbonImmutable $starts_at
 * @property int $capacity
 * @property EventRegistrationMode $registration_mode
 * @property CarbonImmutable|null $cancelled_at
 * @property-read User $organizer
 * @property-read Collection<int, EventRegistration> $registrations
 */
#[Fillable([
    'organizer_user_id',
    'title',
    'description',
    'general_location',
    'detailed_location',
    'starts_at',
    'capacity',
    'registration_mode',
    'cancelled_at',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_user_id');
    }

    /** @return HasMany<EventRegistration, $this> */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function occupiedPlaces(): int
    {
        $acceptedRegistrations = array_key_exists('accepted_registrations_count', $this->attributes)
            ? (int) $this->attributes['accepted_registrations_count']
            : $this->registrations()
                ->where('status', EventRegistrationStatus::Accepted)
                ->count();

        return 1 + $acceptedRegistrations;
    }

    /** @param Builder<Event> $query */
    public function scopeWithAcceptedRegistrationCount(Builder $query): void
    {
        $query->withCount([
            'registrations as accepted_registrations_count' => fn (Builder $registrations) => $registrations
                ->where('status', EventRegistrationStatus::Accepted),
        ]);
    }

    /** @param Builder<Event> $query */
    public function scopeWithAvailableCapacity(Builder $query): void
    {
        $query->whereRaw(
            'events.capacity > 1 + (select count(*) from event_registrations where event_registrations.event_id = events.id and event_registrations.status = ?)',
            [EventRegistrationStatus::Accepted->value],
        );
    }

    public function hasStarted(): bool
    {
        return now()->greaterThanOrEqualTo($this->starts_at);
    }

    public function majorChangesAllowed(): bool
    {
        return now()->lessThanOrEqualTo($this->starts_at->subDay());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'registration_mode' => EventRegistrationMode::class,
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
