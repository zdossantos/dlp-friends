<?php

use App\Actions\BuildUserDataExport;
use App\Enums\UserStatus;
use App\Models\Block;
use App\Models\Interest;
use App\Models\User;
use App\Services\DiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 20)->startOfDay());
});

test('account settings expose adult defaults based on current age', function (string $birthDate, int $min, int $max) {
    $member = User::factory()->withProfile()->create(['birth_date' => $birthDate]);
    $this->actingAs($member)->get(route('account.edit'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('discoveryPreferences', [
            'min_age' => $min, 'max_age' => $max, 'min_common_interests' => 0,
        ]));
})->with([
    ['1996-10-20', 25, 35],
    ['2008-10-20', 18, 23],
    ['2006-10-21', 18, 24],
]);

test('a member saves private discovery filters independently of account details', function () {
    $member = User::factory()->withProfile()->create();
    $other = User::factory()->withProfile()->create();
    $this->actingAs($member)->patch('/settings/discovery-preferences', [
        'min_age' => 18, 'max_age' => 42, 'min_common_interests' => 2,
        'user_id' => $other->id, 'email' => 'ignored@example.test',
    ])->assertRedirect(route('account.edit'));
    $this->actingAs($member->fresh())->get(route('account.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('discoveryPreferences', [
            'min_age' => 18, 'max_age' => 42, 'min_common_interests' => 2,
        ]));
    expect($member->fresh()->email)->toBe($member->email)
        ->and($other->fresh()->discoveryPreferences())->toBe([
            'min_age' => 20, 'max_age' => 30, 'min_common_interests' => 0,
        ]);
    expect(app(BuildUserDataExport::class)->handle($member->fresh())['discovery_preferences'])->toBe([
        'min_age' => 18, 'max_age' => 42, 'min_common_interests' => 2,
    ]);
});

test('discovery filters reject invalid values without saving partial preferences', function (array $values, string $error) {
    $member = User::factory()->withProfile()->create();
    $this->actingAs($member)->patch('/settings/discovery-preferences', $values + [
        'min_age' => 20, 'max_age' => 30, 'min_common_interests' => 0,
    ])->assertSessionHasErrors($error);
    expect($member->fresh()->discovery_min_age)->toBeNull();
})->with([
    [['min_age' => 17], 'min_age'],
    [['max_age' => 121], 'max_age'],
    [['min_age' => 31], 'max_age'],
    [['min_age' => null], 'min_age'],
    [['min_age' => 20.5], 'min_age'],
    [['min_common_interests' => -1], 'min_common_interests'],
    [['min_common_interests' => 101], 'min_common_interests'],
    [['min_common_interests' => 'invalid'], 'min_common_interests'],
]);

test('default suggestions respect inclusive age boundaries using birthdays', function () {
    $actor = User::factory()->withProfile()->create(['birth_date' => '1996-10-20']);
    $youngest = User::factory()->withProfile()->create(['birth_date' => '2001-10-20']);
    $oldest = User::factory()->withProfile()->create(['birth_date' => '1990-10-21']);
    User::factory()->withProfile()->create(['birth_date' => '2001-10-21']);
    User::factory()->withProfile()->admin()->create(['birth_date' => '1990-10-20']);
    expect(app(DiscoveryService::class)->for($actor)->pluck('userId')->sort()->values()->all())
        ->toBe([$youngest->id, $oldest->id]);
});

test('age and shared active selected interests are combined without applying the targets preferences', function () {
    $actor = User::factory()->withProfile()->create();
    $actor->forceFill(['discovery_min_age' => 18, 'discovery_max_age' => 40, 'discovery_min_common_interests' => 2])->save();
    $interests = Interest::factory()->count(3)->create();
    $actor->profile->interests()->attach($interests->modelKeys());
    $eligible = User::factory()->withProfile()->create();
    $eligible->forceFill(['discovery_min_age' => 60, 'discovery_max_age' => 70])->save();
    $eligible->profile->interests()->attach($interests->take(2)->modelKeys());
    $tooFew = User::factory()->withProfile()->create();
    $tooFew->profile->interests()->attach($interests->first());
    $tooOld = User::factory()->withProfile()->create(['birth_date' => '1980-10-20']);
    $tooOld->profile->interests()->attach($interests->modelKeys());
    $suspended = User::factory()->withProfile()->create();
    $suspended->profile->interests()->attach($interests->modelKeys(), ['is_selected' => false]);
    expect(app(DiscoveryService::class)->for($actor)->pluck('userId')->all())->toBe([$eligible->id]);
    $interests->first()->update(['is_active' => false]);
    expect(app(DiscoveryService::class)->for($actor))->toBeEmpty();
});

test('queue hints cannot restore profiles excluded by either saved filter', function () {
    $actor = User::factory()->withProfile()->create();
    $actor->forceFill(['discovery_min_age' => 30, 'discovery_max_age' => 40, 'discovery_min_common_interests' => 1])->save();
    $young = User::factory()->withProfile()->create();
    $noCommon = User::factory()->withProfile()->create(['birth_date' => '1991-10-20']);
    $this->actingAs($actor)->get(route('discovery.index', ['queue' => [$young->id, $noCommon->id]]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $deferred) => $deferred->where('suggestions', [])));
});

test('broad filters preserve safety exclusions', function (string $reason) {
    $actor = User::factory()->withProfile()->create();
    $actor->forceFill(['discovery_min_age' => 18, 'discovery_max_age' => 120])->save();
    $target = User::factory()->withProfile()->create();
    match ($reason) {
        'hidden' => $target->profile->update(['visibility' => 'hidden']),
        'banned' => $target->forceFill(['status' => UserStatus::Banned])->save(),
        'minor' => $target->update(['birth_date' => today()->subYears(17)]),
        'blocked' => Block::factory()->create(['blocker_user_id' => $actor->id, 'blocked_user_id' => $target->id]),
        'blocking' => Block::factory()->create(['blocker_user_id' => $target->id, 'blocked_user_id' => $actor->id]),
    };
    expect(app(DiscoveryService::class)->for($actor))->toBeEmpty();
})->with(['hidden', 'banned', 'minor', 'blocked', 'blocking']);

test('guests and partner only accounts cannot edit member discovery preferences', function () {
    $this->patch('/settings/discovery-preferences')->assertRedirect(route('login'));
    $partner = User::factory()->partnerOnly()->create();
    $this->actingAs($partner)->patch('/settings/discovery-preferences')->assertForbidden();
});

test('age limits respect birthdays on leap day', function () {
    $this->travelTo(now()->setDate(2028, 2, 29)->startOfDay());
    $actor = User::factory()->withProfile()->create(['birth_date' => '1998-02-28']);
    $youngest = User::factory()->withProfile()->create(['birth_date' => '2003-02-28']);
    User::factory()->withProfile()->create(['birth_date' => '2003-03-01']);
    expect(app(DiscoveryService::class)->for($actor)->pluck('userId')->all())->toBe([$youngest->id]);
});
