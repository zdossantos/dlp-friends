<?php

namespace Tests\Feature;

use App\Enums\SwipeDecision;
use App\Models\Interest;
use App\Models\MemberMatch;
use App\Models\Profile;
use App\Models\Swipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DiscoveryPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inertia.testing.ensure_pages_exist', false);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/discover')->assertRedirect(route('login'));
    }

    public function test_complete_members_preload_up_to_five_public_suggestions(): void
    {
        $interest = Interest::factory()->create(['name' => 'Attractions']);
        $actor = User::factory()->withProfile()->create();
        $target = User::factory()->withProfile()->create();
        User::factory()->withProfile()->count(5)->create();
        $actor->profile?->interests()->attach($interest);
        $target->profile?->interests()->attach($interest);

        $this->actingAs($actor)
            ->get(route('discovery.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Discovery/Index')
                ->where('match', null)
                ->missing('suggestions')
                ->loadDeferredProps(fn (Assert $deferred) => $deferred
                    ->has('suggestions', 5)
                    ->where('suggestions.0.displayName', $target->profile?->display_name)
                    ->where('suggestions.0.commonInterests', ['Attractions'])
                    ->missing('suggestions.0.email')));
    }

    public function test_complete_members_see_an_empty_suggestion_stack_when_no_profile_is_available(): void
    {
        $actor = User::factory()->withProfile()->create();

        $this->actingAs($actor)
            ->get(route('discovery.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Discovery/Index')
                ->where('match', null)
                ->missing('suggestions')
                ->loadDeferredProps(fn (Assert $deferred) => $deferred
                    ->where('suggestions', [])));
    }

    public function test_swipe_decision_rejects_unknown_values(): void
    {
        [$actor, $target] = $this->memberPair();

        $this->actingAs($actor)
            ->post(route('discovery.swipe', $target), ['decision' => 'super-like'])
            ->assertSessionHasErrors('decision');

        $this->assertDatabaseCount('swipes', 0);
        $this->assertDatabaseCount('matches', 0);
    }

    public function test_missing_and_ineligible_targets_share_the_same_generic_validation_error(): void
    {
        $actor = User::factory()->withProfile()->create();
        $hiddenTarget = User::factory()->withProfile()->create();
        $hiddenTarget->profile?->update(['visibility' => 'hidden']);
        $missingTargetId = User::query()->max('id') + 1000;

        foreach ([$hiddenTarget->id, $missingTargetId, 'not-a-user'] as $targetId) {
            $this->actingAs($actor)
                ->post(route('discovery.swipe', $targetId), [
                    'decision' => SwipeDecision::Like->value,
                ])
                ->assertSessionHasErrors([
                    'target' => 'Ce profil n’est pas disponible.',
                ]);
        }

        $this->assertDatabaseCount('swipes', 0);
        $this->assertDatabaseCount('matches', 0);
    }

    public function test_successful_swipe_redirects_and_excludes_that_profile_from_next_suggestion(): void
    {
        [$actor, $target] = $this->memberPair();

        $this->actingAs($actor)
            ->post(route('discovery.swipe', $target), ['decision' => SwipeDecision::Pass->value])
            ->assertRedirect(route('discovery.index'));

        $this->actingAs($actor)
            ->get(route('discovery.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Discovery/Index')
                ->where('match', null)
                ->missing('suggestions')
                ->loadDeferredProps(fn (Assert $deferred) => $deferred
                    ->where('suggestions', [])));
    }

    public function test_reciprocal_like_flashes_the_match_contract_once(): void
    {
        [$actor, $target] = $this->memberPair();
        Swipe::factory()->create([
            'actor_user_id' => $target->id,
            'target_user_id' => $actor->id,
            'decision' => SwipeDecision::Like,
        ]);

        $this->actingAs($actor)
            ->post(route('discovery.swipe', $target), ['decision' => SwipeDecision::Like->value])
            ->assertRedirect(route('discovery.index'));

        [$lowId, $highId] = collect([$actor->id, $target->id])->sort()->values()->all();
        $matchId = MemberMatch::query()
            ->where('user_low_id', $lowId)
            ->where('user_high_id', $highId)
            ->firstOrFail()
            ->id;

        $this->actingAs($actor)
            ->get(route('discovery.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Discovery/Index')
                ->where('match.id', $matchId)
                ->where('match.displayName', $target->profile?->display_name)
                ->missing('match.email'));

        $this->actingAs($actor)
            ->get(route('discovery.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('match', null));
    }

    /** @return array{User, User} */
    private function memberPair(): array
    {
        return [$this->member(), $this->member()];
    }

    private function member(): User
    {
        $user = User::factory()->create();

        Profile::factory()->complete()->for($user)->create();

        return $user;
    }
}
