<?php

namespace App\Models;

use App\Enums\ProfileVisibility;
use App\Enums\RoleName;
use App\Enums\TripAudience;
use App\Enums\TripDirection;
use App\Enums\TripRegistrationStatus;
use App\Enums\TripTransport;
use App\Enums\UserStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TripFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int|null $organizer_user_id
 * @property TripTransport $transport
 * @property TripDirection $direction
 * @property string $general_departure
 * @property string $destination
 * @property CarbonImmutable|null $arrives_at
 * @property CarbonImmutable $departs_at
 * @property string $meeting_point
 * @property TripAudience $audience
 * @property int|null $car_seats
 * @property CarbonImmutable|null $first_accepted_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property-read GroupChat|null $chat
 * @property-read User|null $organizer
 */
#[Fillable(['transport', 'direction', 'general_departure', 'destination', 'departs_at', 'arrives_at', 'meeting_point', 'audience', 'car_seats', 'first_accepted_at', 'cancelled_at', 'cancellation_reason'])]
class Trip extends Model
{
    /** @use HasFactory<TripFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_user_id');
    }

    /** @return HasOne<GroupChat, $this> */
    public function chat(): HasOne
    {
        return $this->hasOne(GroupChat::class);
    }

    /** @return HasMany<TripRegistration, $this> */
    public function registrations(): HasMany
    {
        return $this->hasMany(TripRegistration::class);
    }

    /** @return HasMany<TripDeparture, $this> */
    public function departures(): HasMany
    {
        return $this->hasMany(TripDeparture::class);
    }

    public function remainingPlaces(): int
    {
        $capacity = $this->transport === TripTransport::Car ? ($this->car_seats ?? 0) : 49;

        $acceptedRegistrations = array_key_exists('accepted_registrations_count', $this->attributes)
            ? (int) $this->attributes['accepted_registrations_count']
            : $this->registrations()->where('status', TripRegistrationStatus::Accepted)->count();

        return max(0, $capacity - $acceptedRegistrations);
    }

    /** @param Builder<Trip> $query */
    public function scopeWithAcceptedRegistrationCount(Builder $query): void
    {
        $query->withCount([
            'registrations as accepted_registrations_count' => fn (Builder $registrations) => $registrations
                ->where('status', TripRegistrationStatus::Accepted),
        ]);
    }

    /** @param Builder<Trip> $query */
    public function scopeRequestableBy(Builder $query, User $viewer): void
    {
        $query->visibleTo($viewer)->where('organizer_user_id', '!=', $viewer->id)
            ->whereNull('cancelled_at')->where('departs_at', '>', now())
            ->whereRaw(
                '(select count(*) from trip_registrations where trip_registrations.trip_id = trips.id and trip_registrations.status = ?) < CASE WHEN trips.transport = ? THEN COALESCE(trips.car_seats, 0) ELSE 49 END',
                [TripRegistrationStatus::Accepted->value, TripTransport::Car->value],
            )
            ->whereDoesntHave('registrations', fn (Builder $registration) => $registration
                ->where('status', TripRegistrationStatus::Accepted)
                ->whereHas('user', fn (Builder $participant) => $participant
                    ->whereHas('blocksCreated', fn (Builder $blocks) => $blocks->where('blocked_user_id', $viewer->id))
                    ->orWhereHas('blocksReceived', fn (Builder $blocks) => $blocks->where('blocker_user_id', $viewer->id))));
    }

    /** @param Builder<Trip> $query */
    public function scopeSearching(Builder $query, ?string $search): void
    {
        if ($search === null || trim($search) === '') {
            return;
        }
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($search)).'%';
        $query->where(fn (Builder $places) => $places->where('general_departure', 'like', $pattern)->orWhere('destination', 'like', $pattern));
    }

    /** @param Builder<Trip> $query */
    public function scopeVisibleTo(Builder $query, User $viewer): void
    {
        if ($viewer->status !== UserStatus::Active || ! $viewer->hasVerifiedEmail() || $viewer->age < 18 || ! $viewer->hasRole(RoleName::User) || ! $viewer->profile?->isComplete()) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->whereHas('organizer', function (Builder $owner) use ($viewer): void {
            $owner->where('status', UserStatus::Active)->whereNotNull('email_verified_at')
                ->where('birth_date', '<=', CarbonImmutable::today('Europe/Paris')->subYears(18)->toDateString())
                ->whereHas('roles', fn (Builder $roles) => $roles->where('name', RoleName::User))
                ->whereDoesntHave('blocksCreated', fn (Builder $blocks) => $blocks->where('blocked_user_id', $viewer->id))
                ->whereDoesntHave('blocksReceived', fn (Builder $blocks) => $blocks->where('blocker_user_id', $viewer->id));
        })->where(function (Builder $visible) use ($viewer): void {
            $visible->where('organizer_user_id', $viewer->id)
                ->orWhereHas('registrations', fn (Builder $registration) => $registration->where('user_id', $viewer->id)->where('status', TripRegistrationStatus::Accepted))
                ->orWhere(function (Builder $public) use ($viewer): void {
                    $public->whereNull('cancelled_at')->where('departs_at', '>', now())
                        ->whereHas('organizer.profile', fn (Builder $profile) => $profile->where('visibility', ProfileVisibility::Visible)->whereNotNull('onboarding_completed_at')->whereHas('avatar', fn (Builder $avatar) => $avatar->where('is_active', true)))
                        ->where(function (Builder $audience) use ($viewer): void {
                            $audience->where('audience', TripAudience::Members)->orWhere(function (Builder $matches) use ($viewer): void {
                                $matches->where('audience', TripAudience::Matches)->whereHas('organizer', fn (Builder $owner) => $owner
                                    ->where(fn (Builder $paired) => $paired->whereHas('lowMatches', fn (Builder $match) => $match->where('user_high_id', $viewer->id))->orWhereHas('highMatches', fn (Builder $match) => $match->where('user_low_id', $viewer->id))));
                            });
                        });
                });
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['transport' => TripTransport::class, 'direction' => TripDirection::class, 'audience' => TripAudience::class, 'departs_at' => 'immutable_datetime', 'arrives_at' => 'immutable_datetime', 'first_accepted_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime', 'car_seats' => 'integer'];
    }
}
