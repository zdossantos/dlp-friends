<?php

use App\Actions\BuildUserDataExport;
use App\Enums\UserStatus;
use App\Jobs\PurgeDeletedUser;
use App\Models\Avatar;
use App\Models\Block;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function passProfilePayload(array $changes = []): array
{
    return [...[
        'display_name' => 'Park Friend',
        'visit_frequency' => 'often',
        'visibility' => 'visible',
    ], ...$changes];
}

test('members can save each exact pass alongside Cast Member without gaining roles', function (string $pass) {
    $owner = User::factory()->withProfile()->create();
    $roles = $owner->roles->modelKeys();
    $this->actingAs($owner)->patch(route('member-profile.update'), passProfilePayload([
        'disneyland_pass' => $pass, 'is_cast_member' => true,
    ]))->assertSessionHasNoErrors();
    expect($owner->profile->fresh()->getRawOriginal('disneyland_pass'))->toBe($pass)
        ->and($owner->profile->fresh()->getAttribute('is_cast_member'))->toBeTrue()
        ->and($owner->fresh()->roles->modelKeys())->toBe($roles);
})->with(['bronze_one', 'bronze_max', 'silver', 'gold', 'bronze']);

test('pass and Cast Member are optional during profile creation', function (bool $withStatus) {
    $avatar = Avatar::factory()->create();
    $owner = User::factory()->create();
    $this->actingAs($owner)->post(route('member-profile.store'), passProfilePayload([
        'avatar_id' => $avatar->id,
        ...($withStatus ? ['disneyland_pass' => 'bronze', 'is_cast_member' => true] : []),
    ]))->assertSessionHasNoErrors();
    expect($owner->fresh()->profile->getRawOriginal('disneyland_pass'))->toBe($withStatus ? 'bronze' : null)
        ->and($owner->fresh()->profile->getAttribute('is_cast_member'))->toBe($withStatus);
})->with([true, false]);

test('members can change and remove both statuses while omitted fields preserve their choices', function () {
    $owner = User::factory()->withProfile()->create();
    $this->actingAs($owner)->patch(route('member-profile.update'), passProfilePayload(['disneyland_pass' => 'gold', 'is_cast_member' => true]))->assertSessionHasNoErrors();
    $this->patch(route('member-profile.update'), passProfilePayload(['disneyland_pass' => 'silver']))->assertSessionHasNoErrors();
    expect($owner->profile->fresh()->getRawOriginal('disneyland_pass'))->toBe('silver')
        ->and($owner->profile->fresh()->getAttribute('is_cast_member'))->toBeTrue();
    $this->patch(route('member-profile.update'), passProfilePayload(['disneyland_pass' => null, 'is_cast_member' => false]))->assertSessionHasNoErrors();
    expect($owner->profile->fresh()->getRawOriginal('disneyland_pass'))->toBeNull()
        ->and($owner->profile->fresh()->getAttribute('is_cast_member'))->toBeFalse();
    $this->patch(route('member-profile.update'), passProfilePayload(['is_cast_member' => true]))->assertSessionHasNoErrors();
    expect($owner->profile->fresh()->getRawOriginal('disneyland_pass'))->toBeNull()
        ->and($owner->profile->fresh()->getAttribute('is_cast_member'))->toBeTrue();
});

test('unsupported status values are rejected without changing the profile', function (array $status, string $field) {
    $owner = User::factory()->withProfile()->create();
    $this->actingAs($owner)->patch(route('member-profile.update'), passProfilePayload($status))
        ->assertSessionHasErrors($field);
    expect($owner->profile->fresh()->getRawOriginal('disneyland_pass'))->toBeNull();
})->with([
    [['disneyland_pass' => 'premium'], 'disneyland_pass'],
    [['disneyland_pass' => ['gold']], 'disneyland_pass'],
    [['is_cast_member' => 'yes'], 'is_cast_member'],
    [['is_cast_member' => null], 'is_cast_member'],
]);

test('statuses belong to the authenticated member and appear on authorized detailed profiles and exports', function () {
    $owner = User::factory()->withProfile()->create();
    $viewer = User::factory()->withProfile()->create();
    $this->actingAs($owner)->patch(route('member-profile.update'), passProfilePayload(['disneyland_pass' => 'gold', 'is_cast_member' => true, 'user_id' => $viewer->id]))->assertSessionHasNoErrors();
    expect($viewer->profile->fresh()->getRawOriginal('disneyland_pass'))->toBeNull();
    $this->get(route('member-profile.show'))->assertInertia(fn (Assert $page) => $page->where('profile.disneyland_pass', 'gold')->where('profile.is_cast_member', true));
    $export = app(BuildUserDataExport::class)->handle($owner->fresh());
    expect($export['profile']['disneyland_pass'] ?? null)->toBe('gold')
        ->and($export['profile']['is_cast_member'] ?? null)->toBeTrue();
    $event = Event::factory()->create(['organizer_user_id' => $owner->id]);
    EventRegistration::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $viewer->id]);
    foreach ([[route('members.show', $owner), 'member'], [route('events.participants.show', [$event, $owner]), 'panel.profile.member']] as [$url, $key]) {
        $this->actingAs($viewer)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where($key.'.disneyland_pass', 'gold')->where($key.'.is_cast_member', true));
    }
});

test('discovery shares optional pass and Cast Member statuses with eligible members', function (?string $pass, bool $castMember) {
    $viewer = User::factory()->withProfile()->create();
    $owner = User::factory()->withProfile()->create();
    $owner->profile->update(['disneyland_pass' => $pass, 'is_cast_member' => $castMember]);

    $this->actingAs($viewer)->get(route('discovery.index'))->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $deferred) => $deferred
            ->has('suggestions', 1)
            ->where('suggestions.0.disneylandPass', $pass)
            ->where('suggestions.0.isCastMember', $castMember)));
})->with([
    ['bronze_one', true], ['bronze_max', false], ['silver', true], ['gold', false], ['bronze', true], [null, true], [null, false],
]);

test('blocked pairs cannot read statuses even when a basic profile remains accessible', function (bool $reverse) {
    $owner = User::factory()->withProfile()->create();
    $viewer = User::factory()->withProfile()->create();
    $this->actingAs($owner)->patch(route('member-profile.update'), passProfilePayload(['disneyland_pass' => 'gold', 'is_cast_member' => true]))->assertSessionHasNoErrors();
    Block::factory()->create(['blocker_user_id' => $reverse ? $owner->id : $viewer->id, 'blocked_user_id' => $reverse ? $viewer->id : $owner->id]);
    $this->actingAs($viewer)->get(route('members.show', $owner))->assertOk()->assertInertia(fn (Assert $page) => $page->missing('member.disneyland_pass')->missing('member.is_cast_member'));
})->with([true, false]);

test('hidden and unavailable profiles do not expose pass statuses', function () {
    $owner = User::factory()->withProfile()->create();
    $viewer = User::factory()->withProfile()->create();
    $this->actingAs($owner)->patch(route('member-profile.update'), passProfilePayload(['disneyland_pass' => 'gold', 'is_cast_member' => true, 'visibility' => 'hidden']))->assertSessionHasNoErrors();
    $this->actingAs($viewer)->get(route('members.show', $owner))->assertNotFound();
    $owner->profile->update(['visibility' => 'visible']);
    $owner->forceFill(['status' => UserStatus::PendingDeletion])->save();
    $this->get(route('members.show', $owner))->assertNotFound();
});

test('profile statuses are purged with the profile', function () {
    $owner = User::factory()->withProfile()->create();
    $this->actingAs($owner)->patch(route('member-profile.update'), passProfilePayload(['disneyland_pass' => 'gold', 'is_cast_member' => true]))->assertSessionHasNoErrors();
    $requestedAt = now()->subDays(31);
    $owner->forceFill(['status' => UserStatus::PendingDeletion, 'deletion_requested_at' => $requestedAt])->save();
    $profileId = $owner->profile->id;
    (new PurgeDeletedUser($owner->id, $requestedAt->toIso8601String()))->handle();
    $this->assertDatabaseMissing('profiles', ['id' => $profileId]);
});
