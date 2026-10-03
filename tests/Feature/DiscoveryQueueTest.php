<?php

use App\Contracts\DiscoveryTieBreaker;
use App\Models\Block;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
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

test('a swipe carries the remaining queue to the refreshed suggestions', function () {
    $actor = User::factory()->withProfile()->create();
    $target = User::factory()->withProfile()->create();
    $queued = User::factory()->withProfile()->create();

    $this->actingAs($actor)->post(route('discovery.swipe', $target), [
        'decision' => 'pass', 'queue' => [$queued->id],
    ])->assertRedirect(route('discovery.index', ['queue' => [$queued->id]]));
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
