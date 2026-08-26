<?php

namespace Tests\Unit;

use App\Contracts\DiscoveryTieBreaker;
use App\Enums\ProfileVisibility;
use App\Enums\SwipeDecision;
use App\Enums\UserStatus;
use App\Enums\VisitFrequency;
use App\Models\Block;
use App\Models\Interest;
use App\Models\Profile;
use App\Models\Swipe;
use App\Models\User;
use App\Services\DiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiscoveryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_orders_profiles_by_common_interests_then_frequency_bonus(): void
    {
        [$attractions, $parades, $hotels] = $this->interests('Attractions', 'Parades', 'Hotels');
        $actor = $this->member('Actor', VisitFrequency::Often, [$attractions, $parades]);
        $oneCommon = $this->member('One common', VisitFrequency::Rarely, [$attractions, $hotels]);
        $sameFrequency = $this->member('Same frequency', VisitFrequency::Often, [$attractions]);
        $twoCommon = $this->member('Two common', VisitFrequency::Rarely, [$attractions, $parades]);

        $tieBreaker = new class implements DiscoveryTieBreaker
        {
            public function rank(int $profileId): int
            {
                return $profileId;
            }
        };

        $results = (new DiscoveryService($tieBreaker))->for($actor);

        expect($results->pluck('profileId')->all())->toBe([
            $twoCommon->profile->id,
            $sameFrequency->profile->id,
            $oneCommon->profile->id,
        ]);
        expect($results[1]->score)->toBe(1.25)
            ->and($results[1]->commonInterests)->toBe(['Attractions'])
            ->and($results[1]->interests)->toBe([
                ['name' => 'Attractions', 'isCommon' => true],
            ])
            ->and($results[2]->interests)->toBe([
                ['name' => 'Attractions', 'isCommon' => true],
                ['name' => 'Hotels', 'isCommon' => false],
            ])
            ->and($results[1]->frequencyBonus)->toBeTrue();
    }

    public function test_rank_only_orders_profiles_with_identical_common_interests_and_frequency_bonus(): void
    {
        [$attractions, $parades] = $this->interests('Attractions', 'Parades');
        $actor = $this->member('Actor', VisitFrequency::Sometimes, [$attractions, $parades]);
        $higherRank = $this->member('Higher rank', VisitFrequency::Rarely, [$attractions]);
        $lowerRank = $this->member('Lower rank', VisitFrequency::Rarely, [$attractions]);

        $tieBreaker = new class($lowerRank->profile->id, $higherRank->profile->id) implements DiscoveryTieBreaker
        {
            public function __construct(private readonly int $firstProfileId, private readonly int $secondProfileId) {}

            public function rank(int $profileId): int
            {
                return match ($profileId) {
                    $this->firstProfileId => 10,
                    $this->secondProfileId => 20,
                    default => 30,
                };
            }
        };

        $results = (new DiscoveryService($tieBreaker))->for($actor);

        expect($results->pluck('profileId')->all())->toBe([
            $lowerRank->profile->id,
            $higherRank->profile->id,
        ]);
    }

    public function test_it_excludes_profiles_that_are_not_discoverable_for_the_actor(): void
    {
        [$attractions] = $this->interests('Attractions');
        $actor = $this->member('Actor', VisitFrequency::Often, [$attractions]);
        $eligible = $this->member('Eligible', VisitFrequency::Often, [$attractions]);
        $this->member('Hidden', VisitFrequency::Often, [$attractions], [
            'visibility' => ProfileVisibility::Hidden,
        ]);
        $this->member('Incomplete', VisitFrequency::Often, [$attractions], [
            'onboarding_completed_at' => null,
        ]);
        $this->member('Pending deletion', VisitFrequency::Often, [$attractions], userAttributes: [
            'status' => UserStatus::PendingDeletion,
        ]);
        $this->member('Minor', VisitFrequency::Often, [$attractions], userAttributes: [
            'birth_date' => today()->subYears(17),
        ]);
        $this->member('No birth date', VisitFrequency::Often, [$attractions], userAttributes: [
            'birth_date' => null,
        ]);
        $alreadySwiped = $this->member('Already swiped', VisitFrequency::Often, [$attractions]);
        $blockedByActor = $this->member('Blocked by actor', VisitFrequency::Often, [$attractions]);
        $blockedActor = $this->member('Blocked actor', VisitFrequency::Often, [$attractions]);

        Swipe::factory()->create([
            'actor_user_id' => $actor->id,
            'target_user_id' => $alreadySwiped->id,
            'decision' => SwipeDecision::Pass,
        ]);
        Block::factory()->create([
            'blocker_user_id' => $actor->id,
            'blocked_user_id' => $blockedByActor->id,
        ]);
        Block::factory()->create([
            'blocker_user_id' => $blockedActor->id,
            'blocked_user_id' => $actor->id,
        ]);

        $results = (new DiscoveryService($this->ascendingTieBreaker()))->for($actor);

        expect($results->pluck('profileId')->all())->toBe([$eligible->profile->id]);
    }

    public function test_it_serializes_discovery_profiles_with_the_frontend_camel_case_contract(): void
    {
        [$attractions] = $this->interests('Attractions');
        $actor = $this->member('Actor', VisitFrequency::Often, [$attractions]);
        $target = $this->member('Target', VisitFrequency::Often, [$attractions], [
            'bio' => 'Parade fan.',
        ], [
            'birth_date' => today()->subYears(30),
        ]);

        $result = (new DiscoveryService($this->ascendingTieBreaker()))->for($actor)->first();

        expect($result?->toArray())->toBe([
            'userId' => $target->id,
            'profileId' => $target->profile->id,
            'displayName' => 'Target',
            'avatar' => [
                'id' => $target->profile->avatar->id,
                'name' => $target->profile->avatar->name,
                'image_url' => route('avatars.image', $target->profile->avatar),
                'primary_color' => $target->profile->avatar->primary_color,
                'secondary_color' => $target->profile->avatar->secondary_color,
            ],
            'age' => 30,
            'bio' => 'Parade fan.',
            'visitFrequency' => VisitFrequency::Often->value,
            'commonInterestCount' => 1,
            'commonInterests' => ['Attractions'],
            'interests' => [
                ['name' => 'Attractions', 'isCommon' => true],
            ],
            'frequencyBonus' => true,
            'score' => 1.25,
        ]);
    }

    public function test_inactive_interests_do_not_contribute_to_the_score_or_explanation(): void
    {
        [$activeInterest] = $this->interests('Attractions');
        $inactiveInterest = Interest::factory()->create([
            'name' => 'Archived interest',
            'is_active' => false,
        ]);
        $actor = $this->member(
            'Actor',
            VisitFrequency::Rarely,
            [$activeInterest, $inactiveInterest],
        );
        $target = $this->member(
            'Target',
            VisitFrequency::Often,
            [$activeInterest, $inactiveInterest],
        );

        $result = (new DiscoveryService($this->ascendingTieBreaker()))
            ->for($actor)
            ->first();

        expect($result?->profileId)->toBe($target->profile->id)
            ->and($result?->commonInterestCount)->toBe(1)
            ->and($result?->commonInterests)->toBe(['Attractions'])
            ->and($result?->score)->toBe(1.0);
    }

    public function test_a_member_on_their_exact_eighteenth_birthday_is_eligible(): void
    {
        [$interest] = $this->interests('Attractions');
        $actor = $this->member('Actor', VisitFrequency::Often, [$interest]);
        $target = $this->member(
            'Exactly eighteen',
            VisitFrequency::Rarely,
            [$interest],
            userAttributes: ['birth_date' => today()->subYears(18)],
        );

        $results = (new DiscoveryService($this->ascendingTieBreaker()))->for($actor);

        expect($results->pluck('profileId')->all())->toBe([$target->profile->id]);
    }

    public function test_it_uses_a_constant_query_budget_for_candidate_transformation(): void
    {
        [$attractions] = $this->interests('Attractions');
        $actor = $this->member('Actor', VisitFrequency::Often, [$attractions]);

        foreach (range(1, 10) as $index) {
            $this->member("Candidate {$index}", VisitFrequency::Rarely, [$attractions]);
        }

        $queries = [];
        DB::listen(function () use (&$queries): void {
            $queries[] = true;
        });

        (new DiscoveryService($this->ascendingTieBreaker()))->for($actor);

        expect(count($queries))->toBeLessThanOrEqual(6);
    }

    /** @return list<Interest> */
    private function interests(string ...$names): array
    {
        return array_map(
            fn (string $name): Interest => Interest::factory()->create(['name' => $name, 'is_active' => true]),
            $names,
        );
    }

    /**
     * @param  list<Interest>  $interests
     * @param  array<string, mixed>  $profileAttributes
     * @param  array<string, mixed>  $userAttributes
     */
    private function member(
        string $displayName,
        ?VisitFrequency $visitFrequency,
        array $interests,
        array $profileAttributes = [],
        array $userAttributes = [],
    ): User {
        $user = User::factory()->create($userAttributes);
        $profile = Profile::factory()->complete()->for($user)->create(array_merge([
            'display_name' => $displayName,
            'visit_frequency' => $visitFrequency,
        ], $profileAttributes));

        $profile->interests()->attach(array_map(
            fn (Interest $interest): int => $interest->id,
            $interests,
        ));

        return $user->load('profile');
    }

    private function ascendingTieBreaker(): DiscoveryTieBreaker
    {
        return new class implements DiscoveryTieBreaker
        {
            public function rank(int $profileId): int
            {
                return $profileId;
            }
        };
    }
}
