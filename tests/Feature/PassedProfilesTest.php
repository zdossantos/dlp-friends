<?php

use App\Enums\SwipeDecision;
use App\Models\Block;
use App\Models\Swipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function passedProfile(User $actor, array $attributes = []): User
{
    $target = User::factory()->withProfile()->create();
    Swipe::factory()->create([
        'actor_user_id' => $actor->id,
        'target_user_id' => $target->id,
        'decision' => SwipeDecision::Pass,
        ...$attributes,
    ]);

    return $target;
}

test('passed profiles require member access', function () {
    $this->get('/discover/passed')->assertRedirect(route('login'));
    $partner = User::factory()->partnerOnly()->create();
    $this->actingAs($partner)->get('/discover/passed')->assertForbidden();
});

test('only own passes are listed in descending creation order and paginated', function () {
    $actor = User::factory()->withProfile()->create();
    $oldest = passedProfile($actor, ['created_at' => now()->subDays(2), 'updated_at' => now()->addDay()]);
    for ($index = 0; $index < 20; $index++) {
        $latest = passedProfile($actor);
    }
    passedProfile(User::factory()->withProfile()->create());
    passedProfile($actor, ['decision' => SwipeDecision::Like]);

    $this->actingAs($actor)->get('/discover/passed?actor_user_id=999')
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Discovery/Passed')
        ->has('profiles.data', 20)
        ->where('profiles.total', 21)
        ->where('profiles.data.0.id', $latest->id)
        ->missing('profiles.data.0.email'));
    $this->get('/discover/passed?page=2')->assertInertia(fn (Assert $page) => $page
        ->has('profiles.data', 1)->where('profiles.data.0.id', $oldest->id));
});

test('unavailable passed profiles are neither listed nor viewable nor actionable', function (string $reason) {
    $actor = User::factory()->withProfile()->create();
    $target = passedProfile($actor);
    $this->actingAs($actor)->get('/discover/passed/'.$target->id)->assertOk();
    match ($reason) {
        'hidden' => $target->profile->update(['visibility' => 'hidden']),
        'incomplete' => $target->profile->update(['onboarding_completed_at' => null]),
        'avatar' => $target->profile->avatar->update(['is_active' => false]),
        'inactive' => $target->forceFill(['status' => 'pending_deletion'])->save(),
        'minor' => $target->update(['birth_date' => today()->subYears(17)]),
        'blocked' => Block::factory()->create(['blocker_user_id' => $actor->id, 'blocked_user_id' => $target->id]),
        'blocking' => Block::factory()->create(['blocker_user_id' => $target->id, 'blocked_user_id' => $actor->id]),
    };
    $this->actingAs($actor)->get('/discover/passed')->assertInertia(fn (Assert $page) => $page->has('profiles.data', 0));
    $this->get('/discover/passed/'.$target->id)->assertNotFound();
    $this->post('/discover/passed/'.$target->id.'/like')->assertSessionHasErrors('target');
    expect(Swipe::query()->first()->decision)->toBe(SwipeDecision::Pass);
})->with(['hidden', 'incomplete', 'avatar', 'inactive', 'minor', 'blocked', 'blocking']);

test('another members pass does not grant access to the profile or conversion', function () {
    $actor = User::factory()->withProfile()->create();
    $target = passedProfile(User::factory()->withProfile()->create());
    $this->actingAs($actor)->get('/discover/passed/'.$target->id)->assertNotFound();
    $this->post('/discover/passed/'.$target->id.'/like')->assertSessionHasErrors('target');
    $this->assertDatabaseCount('swipes', 1);
});

test('a pass is converted once and reciprocal likes create a single match and conversation', function (bool $reciprocal) {
    $actor = User::factory()->withProfile()->create();
    $target = passedProfile($actor);
    if ($reciprocal) {
        Swipe::factory()->create(['actor_user_id' => $target->id, 'target_user_id' => $actor->id, 'decision' => SwipeDecision::Like]);
    }
    $this->actingAs($actor)->get('/discover/passed/'.$target->id)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Members/Show')->where('canLike', true));
    $this->post('/discover/passed/'.$target->id.'/like')->assertRedirect('/discover/passed');
    $this->get('/discover/passed')->assertInertia(fn (Assert $page) => $page
        ->has('profiles.data', 0)
        ->where('match', $reciprocal ? fn ($match) => $match !== null : null));
    $this->post('/discover/passed/'.$target->id.'/like')->assertSessionHasErrors('target');
    $this->post(route('discovery.swipe', $target), ['decision' => 'pass'])->assertSessionHasErrors('decision');
    $this->assertDatabaseCount('matches', $reciprocal ? 1 : 0);
    $this->assertDatabaseCount('conversations', $reciprocal ? 1 : 0);
    $this->get('/discover')->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $deferred) => $deferred->where('suggestions', [])));
})->with([false, true]);
