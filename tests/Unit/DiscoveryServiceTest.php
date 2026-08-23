<?php

namespace Tests\Unit;

use App\Contracts\DiscoveryTieBreaker;
use App\Enums\ProfileVisibility;
use App\Enums\SwipeDecision;
use App\Enums\UserStatus;
use App\Enums\VisitFrequency;
use App\Models\Block;
use App\Models\Passion;
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

    public function test_it_orders_profiles_by_common_passions_then_frequency_bonus(): void
    {
        [$attractions, $parades, $hotels] = $this->passions('Attractions', 'Parades', 'Hotels');
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
            ->and($results[1]->commonPassions)->toBe(['Attractions'])
            ->and($results[1]->frequencyBonus)->toBeTrue();
    }

    public function test_rank_only_orders_profiles_with_identical_common_passions_and_frequency_bonus(): void
    {
        [$attractions, $parades] = $this->passions('Attractions', 'Parades');
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
        [$attractions] = $this->passions('Attractions');
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
        [$attractions] = $this->passions('Attractions');
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
            'age' => 30,
            'bio' => 'Parade fan.',
            'visitFrequency' => VisitFrequency::Often->value,
            'commonPassionCount' => 1,
            'commonPassions' => ['Attractions'],
            'frequencyBonus' => true,
            'score' => 1.25,
        ]);
    }

    public function test_it_uses_a_constant_query_budget_for_candidate_transformation(): void
    {
        [$attractions] = $this->passions('Attractions');
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

    /** @return list<Passion> */
    private function passions(string ...$names): array
    {
        return array_map(
            fn (string $name): Passion => Passion::factory()->create(['name' => $name, 'is_active' => true]),
            $names,
        );
    }

    /**
     * @param  list<Passion>  $passions
     * @param  array<string, mixed>  $profileAttributes
     * @param  array<string, mixed>  $userAttributes
     */
    private function member(
        string $displayName,
        ?VisitFrequency $visitFrequency,
        array $passions,
        array $profileAttributes = [],
        array $userAttributes = [],
    ): User {
        $user = User::factory()->create($userAttributes);
        $profile = Profile::factory()->complete()->for($user)->create(array_merge([
            'display_name' => $displayName,
            'visit_frequency' => $visitFrequency,
        ], $profileAttributes));

        $profile->passions()->attach(array_map(
            fn (Passion $passion): int => $passion->id,
            $passions,
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
