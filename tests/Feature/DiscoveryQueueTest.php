<?php

use App\Contracts\DiscoveryTieBreaker;
use App\Models\Block;
use App\Models\Swipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withSession([]);
    $this->withCookie(config('session.cookie'), app('session.store')->getId());
    $this->app->bind(DiscoveryTieBreaker::class, fn () => new class implements DiscoveryTieBreaker
    {
        public function rank(int $profileId): int
        {
            return -$profileId;
        }
    });
});

test('a queued eligible profile is kept ahead of new suggestions even outside the ranked batch', function () {
    $actor = User::factory()->withProfile()->create();
    $queued = User::factory()->withProfile()->create();
    User::factory()->withProfile()->count(6)->create();
    $actor->profile->update(['visit_frequency' => null]);

    $this->actingAs($actor)->get(route('discovery.index', ['queue' => [$queued->id]]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $deferred) => $deferred
            ->has('suggestions', 5)
            ->where('suggestions.0.userId', $queued->id)));
});

test('queue hints never restore an ineligible profile', function (string $reason) {
    $actor = User::factory()->withProfile()->create();
    $queued = User::factory()->withProfile()->create();
    $replacement = User::factory()->withProfile()->create();
    match ($reason) {
        'hidden' => $queued->profile->update(['visibility' => 'hidden']),
        'blocked' => Block::factory()->create(['blocker_user_id' => $queued->id, 'blocked_user_id' => $actor->id]),
        'deleted' => $queued->delete(),
        'inactive-avatar' => $queued->profile->avatar->update(['is_active' => false]),
    };

    $this->actingAs($actor)->get(route('discovery.index', ['queue' => [$queued->id]]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $deferred) => $deferred
            ->has('suggestions', 1)
            ->where('suggestions.0.userId', $replacement->id)));
})->with(['hidden', 'blocked', 'deleted', 'inactive-avatar']);

test('a swipe keeps its remaining queue with a clean redirect', function () {
    $this->withHeaders([
        'X-Inertia-Partial-Component' => 'Discovery/Index',
        'X-Inertia-Partial-Data' => 'suggestions',
    ]);
    $actor = User::factory()->withProfile()->create();
    $target = User::factory()->withProfile()->create();
    $queued = User::factory()->withProfile()->create();

    $this->actingAs($actor)->post(route('discovery.swipe', $target), [
        'decision' => 'pass', 'queue' => [$queued->id],
    ])->assertRedirect(route('discovery.index'));

    $this->get(route('discovery.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('suggestions.0.userId', $queued->id));
});

test('queue hints are bounded and validated before recording a decision', function (array $queue) {
    $actor = User::factory()->withProfile()->create();
    $target = User::factory()->withProfile()->create();

    $this->actingAs($actor)->post(route('discovery.swipe', $target), [
        'decision' => 'pass', 'queue' => $queue,
    ])->assertSessionHasErrors();
    $this->assertDatabaseCount('swipes', 0);
    $this->actingAs($actor)->getJson(route('discovery.index', ['queue' => $queue]))->assertUnprocessable();
})->with([
    'too many' => [[1, 2, 3, 4, 5, 6]],
    'duplicate' => [[1, 1]],
    'invalid id' => [['invalid']],
    'negative id' => [[-1]],
]);

test('administrators precede every queued member even when the queue fills the batch', function () {
    $actor = User::factory()->withProfile()->create();
    $queued = User::factory()->withProfile()->count(5)->create();
    $admin = User::factory()->admin()->withProfile()->create();

    $this->actingAs($actor)->get(route('discovery.index', ['queue' => $queued->modelKeys()]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $deferred) => $deferred
            ->has('suggestions', 5)
            ->where('suggestions.0.userId', $admin->id)
            ->where('suggestions.1.userId', $queued[0]->id)
            ->where('suggestions.4.userId', $queued[3]->id)));
});

test('administrator priority never bypasses eligibility or an earlier decision', function (string $reason) {
    $actor = User::factory()->withProfile()->create();
    $admin = User::factory()->admin()->withProfile()->create();
    $member = User::factory()->withProfile()->create();
    match ($reason) {
        'hidden' => $admin->profile->update(['visibility' => 'hidden']),
        'incomplete' => $admin->profile->update(['onboarding_completed_at' => null]),
        'banned' => $admin->forceFill(['status' => 'banned'])->save(),
        'blocked' => Block::factory()->create(['blocker_user_id' => $admin->id, 'blocked_user_id' => $actor->id]),
        'inactive-avatar' => $admin->profile->avatar->update(['is_active' => false]),
        'already-swiped' => Swipe::factory()->create(['actor_user_id' => $actor->id, 'target_user_id' => $admin->id, 'decision' => 'pass']),
    };

    $this->actingAs($actor)->get(route('discovery.index', ['queue' => [$admin->id]]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $deferred) => $deferred
            ->has('suggestions', 1)
            ->where('suggestions.0.userId', $member->id)));
})->with(['hidden', 'incomplete', 'banned', 'blocked', 'inactive-avatar', 'already-swiped']);

test('a clean discovery visit retains the last displayed order and rechecks eligibility', function () {
    $this->withHeaders([
        'X-Inertia-Partial-Component' => 'Discovery/Index',
        'X-Inertia-Partial-Data' => 'suggestions',
    ]);
    $actor = User::factory()->withProfile()->create();
    $first = User::factory()->withProfile()->create();
    $second = User::factory()->withProfile()->create();
    User::factory()->withProfile()->count(6)->create();
    $actor->profile->update(['visit_frequency' => null]);

    $this->actingAs($actor)->get(route('discovery.index', ['queue' => [$first->id, $second->id]]))
        ->assertInertia(fn (Assert $page) => $page->where('suggestions.0.userId', $first->id));

    $first->profile->update(['visibility' => 'hidden']);
    $this->get(route('discovery.index'))
        ->assertInertia(fn (Assert $page) => $page->where('suggestions.0.userId', $second->id));
});

test('an overlapping background session save cannot overwrite the displayed queue', function () {
    $this->withHeaders([
        'X-Inertia-Partial-Component' => 'Discovery/Index',
        'X-Inertia-Partial-Data' => 'suggestions',
    ]);
    $actor = User::factory()->withProfile()->create();
    $target = User::factory()->withProfile()->create();
    $first = User::factory()->withProfile()->create();
    $second = User::factory()->withProfile()->create();
    $this->actingAs($actor)->get(route('discovery.index', ['queue' => [$target->id, $first->id, $second->id]]))
        ->assertInertia(fn (Assert $page) => $page->where('suggestions.0.userId', $target->id));

    // A heartbeat reads this snapshot before the swipe and finishes after it.
    $backgroundSession = clone app('session.store');
    $backgroundSession->start();
    $this->post(route('discovery.swipe', $target), [
        'decision' => 'pass', 'queue' => [$second->id, $first->id],
    ])->assertRedirect(route('discovery.index'));
    $backgroundSession->save();

    $this->withSession($backgroundSession->all())->get(route('discovery.index'))
        ->assertInertia(fn (Assert $page) => $page->where('suggestions.0.userId', $second->id));
});
