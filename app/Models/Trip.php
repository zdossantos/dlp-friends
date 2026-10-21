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

/**
 * @property int $id
 * @property int|null $organizer_user_id
 * @property TripTransport $transport
 * @property TripDirection $direction
 * @property string $general_departure
 * @property string $destination
 * @property CarbonImmutable $departs_at
 * @property string $meeting_point
 * @property TripAudience $audience
 * @property int|null $car_seats
 * @property CarbonImmutable|null $first_accepted_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property-read User|null $organizer
 */
#[Fillable(['transport', 'direction', 'general_departure', 'destination', 'departs_at', 'meeting_point', 'audience', 'car_seats', 'first_accepted_at', 'cancelled_at', 'cancellation_reason'])]
class Trip extends Model
{
    /** @use HasFactory<TripFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_user_id');
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

        return max(0, $capacity - $this->registrations()->where('status', TripRegistrationStatus::Accepted)->count());
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
        return ['transport' => TripTransport::class, 'direction' => TripDirection::class, 'audience' => TripAudience::class, 'departs_at' => 'immutable_datetime', 'first_accepted_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime', 'car_seats' => 'integer'];
    }
}
