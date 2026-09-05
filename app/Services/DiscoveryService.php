<?php

namespace App\Services;

use App\Contracts\DiscoveryTieBreaker;
use App\Data\DiscoveryProfileData;
use App\Enums\ProfileVisibility;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Avatar;
use App\Models\Interest;
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
            ->with(['interests' => $this->activeInterests(...)])
            ->first();

        if (! $actorProfile instanceof Profile) {
            return collect();
        }

        $actorInterestIds = $actorProfile->interests->modelKeys();
        $actorVisitFrequency = $actorProfile->visit_frequency?->value;

        $profiles = Profile::query()
            ->whereKeyNot($actorProfile->id)
            ->where('visibility', ProfileVisibility::Visible->value)
            ->whereNotNull('onboarding_completed_at')
            ->whereHas('avatar', fn (Builder $query) => $query->where('is_active', true))
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
                'avatar',
                'user.roles',
                'interests' => $this->activeInterests(...),
            ])
            ->get();

        $tieRanks = $profiles
            ->mapWithKeys(fn (Profile $profile): array => [$profile->id => $this->tieBreaker->rank($profile->id)]);

        return $profiles
            ->map(function (Profile $profile) use ($actorInterestIds, $actorVisitFrequency): DiscoveryProfileData {
                $avatar = $profile->avatar;

                abort_unless($avatar instanceof Avatar, 500);

                $commonInterests = array_values($profile->interests
                    ->filter(fn (Interest $interest): bool => in_array($interest->id, $actorInterestIds, true))
                    ->map(fn (Interest $interest): string => $interest->display_name)
                    ->all());
                $commonInterestCount = count($commonInterests);
                $interests = array_values($profile->interests
                    ->map(fn (Interest $interest): array => [
                        'name' => $interest->display_name,
                        'isCommon' => in_array($interest->id, $actorInterestIds, true),
                    ])
                    ->all());
                $visitFrequency = $profile->visit_frequency?->value;
                $frequencyBonus = $actorVisitFrequency !== null && $actorVisitFrequency === $visitFrequency;

                return new DiscoveryProfileData(
                    userId: $profile->user->id,
                    profileId: $profile->id,
                    displayName: $profile->display_name,
                    isAdmin: $profile->user->hasRole(RoleName::Admin),
                    avatar: [
                        'id' => $avatar->id,
                        'name' => $avatar->name,
                        'image_url' => route('avatars.image', $avatar),
                        'primary_color' => $avatar->primary_color,
                        'secondary_color' => $avatar->secondary_color,
                    ],
                    age: (int) $profile->user->age,
                    bio: $profile->bio,
                    visitFrequency: $visitFrequency,
                    commonInterestCount: $commonInterestCount,
                    commonInterests: $commonInterests,
                    interests: $interests,
                    frequencyBonus: $frequencyBonus,
                    score: $commonInterestCount + ($frequencyBonus ? 0.25 : 0.0),
                );
            })
            ->sortBy([
                ['commonInterestCount', 'desc'],
                ['frequencyBonus', 'desc'],
                fn (DiscoveryProfileData $left, DiscoveryProfileData $right): int => $tieRanks[$left->profileId] <=> $tieRanks[$right->profileId],
            ])
            ->values();
    }

    /**
     * @param Relation<*, *, *> $query
     */
    private function activeInterests(Relation $query): void
    {
        $query
            ->where('is_active', true)
            ->orderBy('name');
    }
}
