<?php

use App\Actions\CreateSwipe;
use App\Enums\RoleName;
use App\Enums\SwipeDecision;
use App\Models\Role;
use App\Models\Swipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('targets require a verified member role on every social entry point', function (string $state) {
    $actor = User::factory()->withProfile()->create();
    $target = User::factory()->withProfile()->create();
    match ($state) {
        'unverified' => $target->forceFill(['email_verified_at' => null])->save(),
        'partner only' => $target->roles()->sync([Role::query()->where('name', RoleName::Partner)->firstOrFail()->id]),
        'admin only' => $target->roles()->sync([Role::query()->where('name', RoleName::Admin)->firstOrFail()->id]),
    };

    $this->actingAs($actor)->get(route('discovery.index', ['queue' => [$target->id]]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $deferred) => $deferred->has('suggestions', 0)));
    $this->get(route('members.show', $target))->assertNotFound();
    expect(Gate::forUser($actor)->allows('viewSocialLinks', $target->profile))->toBeFalse();
    foreach (['like', 'pass'] as $decision) {
        $this->post(route('discovery.swipe', $target), ['decision' => $decision])->assertSessionHasErrors('target');
    }
    $this->assertDatabaseCount('swipes', 0);

    Swipe::factory()->create(['actor_user_id' => $actor->id, 'target_user_id' => $target->id, 'decision' => SwipeDecision::Pass]);
    $this->get('/discover/passed')->assertInertia(fn (Assert $page) => $page->has('profiles.data', 0));
    $this->get('/discover/passed/'.$target->id)->assertNotFound();
    $this->post('/discover/passed/'.$target->id.'/like')->assertSessionHasErrors('target');
    expect(Swipe::query()->sole()->decision)->toBe(SwipeDecision::Pass);
})->with(['unverified', 'partner only', 'admin only']);

test('a verified member keeps social eligibility with an additional role', function (string $role) {
    $actor = User::factory()->withProfile()->create();
    $target = User::factory()->withProfile()->create();
    $target->roles()->attach(Role::query()->where('name', $role)->firstOrFail());
    $this->actingAs($actor)->get(route('members.show', $target))->assertOk();
    $this->post(route('discovery.swipe', $target), ['decision' => 'pass'])->assertSessionHasNoErrors();
    $this->get('/discover/passed/'.$target->id)->assertOk();
})->with(['partner', 'admin']);

test('swipes recheck a revoked member role under the user lock even with a cached target', function () {
    $actor = User::factory()->withProfile()->create();
    $target = User::factory()->withProfile()->create();
    $target->load('roles');
    $revoked = false;
    User::retrieved(function (User $user) use ($target, &$revoked): void {
        if ($user->is($target) && ! $revoked) {
            $user->roles()->detach();
            $revoked = true;
        }
    });
    try {
        app(CreateSwipe::class)->handle($actor, $target, SwipeDecision::Like);
        $this->fail('The revoked target must be rejected.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('target');
    }
    expect($revoked)->toBeTrue();
    $this->assertDatabaseCount('swipes', 0);
});
