<?php

namespace App\Services;

use App\Contracts\DiscoveryTieBreaker;
use App\Data\DiscoveryProfileData;
use App\Enums\ProfileVisibility;
use App\Enums\UserStatus;
use App\Models\Passion;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

final readonly class DiscoveryService
{
    public function __construct(private DiscoveryTieBreaker $tieBreaker) {}

    /**
     * @return Collection<int, DiscoveryProfileData>
     */
    public function for(User $user): Collection
    {
        $actorProfile = Profile::query()
            ->where('user_id', $user->id)
            ->with(['passions' => $this->activePassions(...)])
            ->first();

        if (! $actorProfile instanceof Profile) {
            return collect();
        }

        $actorPassionIds = $actorProfile->passions->modelKeys();
        $actorVisitFrequency = $actorProfile->visit_frequency?->value;

        $profiles = Profile::query()
            ->whereKeyNot($actorProfile->id)
            ->where('visibility', ProfileVisibility::Visible->value)
            ->whereNotNull('onboarding_completed_at')
            ->whereHas('user', function (Builder $query): void {
                $query
                    ->where('status', UserStatus::Active->value)
                    ->where('birth_date', '<=', today()->subYears(18));
            })
            ->whereDoesntHave('user.receivedSwipes', function (Builder $query) use ($user): void {
                $query->where('actor_user_id', $user->id);
            })
            ->whereDoesntHave('user.blocksReceived', function (Builder $query) use ($user): void {
                $query->where('blocker_user_id', $user->id);
            })
            ->whereDoesntHave('user.blocksCreated', function (Builder $query) use ($user): void {
                $query->where('blocked_user_id', $user->id);
            })
            ->with([
                'user',
                'passions' => $this->activePassions(...),
            ])
            ->get();

        $tieRanks = $profiles
            ->mapWithKeys(fn (Profile $profile): array => [$profile->id => $this->tieBreaker->rank($profile->id)]);

        return $profiles
            ->map(function (Profile $profile) use ($actorPassionIds, $actorVisitFrequency): DiscoveryProfileData {
                $commonPassions = array_values($profile->passions
                    ->filter(fn (Passion $passion): bool => in_array($passion->id, $actorPassionIds, true))
                    ->pluck('name')
                    ->all());
                $commonPassionCount = count($commonPassions);
                $visitFrequency = $profile->visit_frequency?->value;
                $frequencyBonus = $actorVisitFrequency !== null && $actorVisitFrequency === $visitFrequency;

                return new DiscoveryProfileData(
                    userId: $profile->user->id,
                    profileId: $profile->id,
                    displayName: $profile->display_name,
                    age: (int) $profile->user->age,
                    bio: $profile->bio,
                    visitFrequency: $visitFrequency,
                    commonPassionCount: $commonPassionCount,
                    commonPassions: $commonPassions,
                    frequencyBonus: $frequencyBonus,
                    score: $commonPassionCount + ($frequencyBonus ? 0.25 : 0.0),
                );
            })
            ->sortBy([
                ['commonPassionCount', 'desc'],
                ['frequencyBonus', 'desc'],
                fn (DiscoveryProfileData $left, DiscoveryProfileData $right): int => $tieRanks[$left->profileId] <=> $tieRanks[$right->profileId],
            ])
            ->values();
    }

    /**
     * @param Relation<*, *, *> $query
     */
    private function activePassions(Relation $query): void
    {
        $query
            ->where('is_active', true)
            ->orderBy('name');
    }
}
