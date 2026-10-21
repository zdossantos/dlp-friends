<?php

namespace App\Models;

use App\Enums\ProfileVisibility;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Enums\VisitsVisibility;
use Carbon\CarbonImmutable;
use Database\Factories\VisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property-read User $user
 */
#[Fillable(['starts_on', 'ends_on'])]
class Visit extends Model
{
    /** @use HasFactory<VisitFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<Visit> $query */
    public function scopeOverlapping(Builder $query, CarbonImmutable $from, CarbonImmutable $through): void
    {
        $query->where('starts_on', '<=', $through->toDateString())->where('ends_on', '>=', $from->toDateString());
    }

    /** @param Builder<Visit> $query */
    public function scopeVisibleTo(Builder $query, User $viewer): void
    {
        if ($viewer->status !== UserStatus::Active || ! $viewer->hasVerifiedEmail() || $viewer->age < 18 || ! $viewer->hasRole(RoleName::User) || ! $viewer->profile?->isComplete()) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->where('ends_on', '>=', CarbonImmutable::today('Europe/Paris')->toDateString())
            ->where(function (Builder $visible) use ($viewer): void {
                $visible->where('user_id', $viewer->id)->orWhereHas('user', function (Builder $owner) use ($viewer): void {
                    $owner->where('status', UserStatus::Active)->whereNotNull('email_verified_at')
                        ->where('birth_date', '<=', CarbonImmutable::today('Europe/Paris')->subYears(18)->toDateString())
                        ->whereHas('roles', fn (Builder $roles) => $roles->where('name', RoleName::User))
                        ->whereHas('profile', fn (Builder $profile) => $profile->where('visibility', ProfileVisibility::Visible)->whereNotNull('onboarding_completed_at')->whereHas('avatar', fn (Builder $avatar) => $avatar->where('is_active', true)))
                        ->whereDoesntHave('blocksCreated', fn (Builder $blocks) => $blocks->where('blocked_user_id', $viewer->id))
                        ->whereDoesntHave('blocksReceived', fn (Builder $blocks) => $blocks->where('blocker_user_id', $viewer->id))
                        ->where(function (Builder $audience) use ($viewer): void {
                            $audience->where('visits_visibility', VisitsVisibility::Members)
                                ->orWhere(function (Builder $matches) use ($viewer): void {
                                    $matches->where('visits_visibility', VisitsVisibility::Matches)
                                        ->where(fn (Builder $paired) => $paired
                                            ->whereHas('lowMatches', fn (Builder $match) => $match->where('user_high_id', $viewer->id))
                                            ->orWhereHas('highMatches', fn (Builder $match) => $match->where('user_low_id', $viewer->id)));
                                });
                        });
                });
            });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date'];
    }
}
