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
use Illuminate\Support\Facades\DB;

final readonly class DiscoveryService
{
    public function __construct(private DiscoveryTieBreaker $tieBreaker) {}

    /**
     * @param  list<int>  $queue
     * @return Collection<int, DiscoveryProfileData>
     */
    public function for(User $user, array $queue = []): Collection
    {
        $actorProfile = Profile::query()
            ->where('user_id', $user->id)
            ->with(['interests' => $this->activeInterests(...)])
            ->first();

        if (! $actorProfile instanceof Profile) {
            return collect();
        }

        $preferences = $user->discoveryPreferences();
        $actorInterestIds = $actorProfile->interests->modelKeys();
        $actorVisitFrequency = $actorProfile->visit_frequency?->value;

        $eligible = Profile::query()
            ->whereKeyNot($actorProfile->id)
            ->where('visibility', ProfileVisibility::Visible->value)
            ->whereNotNull('onboarding_completed_at')
            ->whereHas('avatar', fn (Builder $query) => $query->where('is_active', true))
            ->whereHas('user', function (Builder $query) use ($preferences): void {
                $query
                    ->where('status', UserStatus::Active->value)
                    ->whereNotNull('email_verified_at')
                    ->whereHas('roles', fn (Builder $roles) => $roles->where('name', RoleName::User))
                    ->where('birth_date', '<=', today()->subYearsNoOverflow($preferences['min_age']))
                    ->where('birth_date', '>', today()->subYearsNoOverflow($preferences['max_age'] + 1));
            })
            ->whereDoesntHave('user.receivedSwipes', function (Builder $query) use ($user): void {
                $query->where('actor_user_id', $user->id);
            })
            ->whereDoesntHave('user.blocksReceived', function (Builder $query) use ($user): void {
                $query->where('blocker_user_id', $user->id);
            })
            ->whereDoesntHave('user.blocksCreated', function (Builder $query) use ($user): void {
                $query->where('blocked_user_id', $user->id);
            });

        $queueOrder = 'CASE profiles.user_id';
        $queueBindings = [];
        foreach ($queue as $position => $id) {
            $queueOrder .= ' WHEN ? THEN ?';
            $queueBindings[] = $id;
            $queueBindings[] = $position;
        }
        $queueOrder = $queue === [] ? '5' : $queueOrder.' ELSE 5 END';
        $candidates = (clone $eligible)->select('profiles.id', 'profiles.user_id')
            ->withExists(['user as is_admin' => fn (Builder $query) => $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', RoleName::Admin))])
            ->withCount(['interests as common_interest_count' => fn (Builder $interests) => $interests->where('is_active', true)->whereIn('interests.id', $actorInterestIds)])
            ->selectRaw('CASE WHEN visit_frequency = ? THEN 1 ELSE 0 END as frequency_bonus', [$actorVisitFrequency])
            ->selectRaw($queueOrder.' as queue_position', $queueBindings);
        $ranked = DB::query()->fromSub($candidates, 'candidates')
            ->where('common_interest_count', '>=', $preferences['min_common_interests'])
            ->orderByDesc('is_admin')->orderBy('queue_position')
            ->orderByDesc('common_interest_count')->orderByDesc('frequency_bonus')->orderBy('id');

        // Only ties at the fifth position need a PHP rank. Stream their IDs;
        // never hydrate the whole audience or truncate a tie before ranking it.
        $selected = [];
        foreach ($ranked->lazy(200) as $candidate) {
            $priority = [-(int) $candidate->is_admin, (int) $candidate->queue_position, -(int) $candidate->common_interest_count, -(int) $candidate->frequency_bonus];
            if (count($selected) === 5 && $priority > array_slice($selected[4]['order'], 0, 4)) {
                break;
            }
            $selected[] = ['id' => (int) $candidate->id, 'is_admin' => (bool) $candidate->is_admin, 'order' => [...$priority, $this->tieBreaker->rank((int) $candidate->id), (int) $candidate->id]];
            usort($selected, fn (array $left, array $right): int => $left['order'] <=> $right['order']);
            $selected = array_slice($selected, 0, 5);
        }
        $positions = array_flip(array_column($selected, 'id'));
        $admins = array_column($selected, 'is_admin', 'id');

        return (clone $eligible)->whereKey(array_keys($positions))
            ->with(['avatar', 'user', 'interests' => $this->activeInterests(...)])->get()
            ->sortBy(fn (Profile $profile): int => $positions[$profile->id])
            ->map(function (Profile $profile) use ($actorInterestIds, $actorVisitFrequency, $admins): DiscoveryProfileData {
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
                    isAdmin: $admins[$profile->id],
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
