<?php

use App\Actions\BuildUserDataExport;
use App\Enums\UserStatus;
use App\Jobs\PurgeDeletedUser;
use App\Models\Block;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MemberMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function socialProfilePayload(array $changes = []): array
{
    return [...[
        'display_name' => 'Park Friend',
        'visit_frequency' => 'often',
        'visibility' => 'visible',
        'social_links_visibility' => 'matches',
        'social_links' => [['network' => 'instagram', 'url' => 'https://www.instagram.com/parkfriend/']],
    ], ...$changes];
}

test('members can save edit and remove up to three optional social links', function () {
    $owner = User::factory()->withProfile()->create();
    $links = [
        ['network' => 'instagram', 'url' => 'https://www.instagram.com/parkfriend/'],
        ['network' => 'tiktok', 'url' => 'https://www.tiktok.com/@parkfriend'],
        ['network' => 'youtube', 'url' => 'https://www.youtube.com/@parkfriend'],
    ];
    $this->actingAs($owner)->patch(route('member-profile.update'), socialProfilePayload(['social_links' => $links]))
        ->assertSessionHasNoErrors();
    expect($owner->profile->fresh()->social_links)->toEqual($links);

    $edited = [['network' => 'facebook', 'url' => 'https://www.facebook.com/parkfriend']];
    $this->patch(route('member-profile.update'), socialProfilePayload(['social_links' => $edited, 'social_links_visibility' => 'members']))
        ->assertSessionHasNoErrors();
    expect($owner->profile->fresh()->social_links)->toEqual($edited);
    $this->patch(route('member-profile.update'), socialProfilePayload(['social_links' => []]))->assertSessionHasNoErrors();
    expect($owner->profile->fresh()->social_links)->toBe([])
        ->and($owner->profile->fresh()->isComplete())->toBeTrue();
});

test('a fourth link and duplicate or unsupported networks are rejected without changing the profile', function (array $links, string $error) {
    $owner = User::factory()->withProfile()->create();
    $this->actingAs($owner)->patch(route('member-profile.update'), socialProfilePayload(['social_links' => $links]))
        ->assertSessionHasErrors($error);
})->with([
    'fourth' => [[
        ['network' => 'instagram', 'url' => 'https://instagram.com/friend'],
        ['network' => 'facebook', 'url' => 'https://facebook.com/friend'],
        ['network' => 'tiktok', 'url' => 'https://tiktok.com/@friend'],
        ['network' => 'x', 'url' => 'https://x.com/friend'],
    ], 'social_links'],
    'duplicate' => [[
        ['network' => 'instagram', 'url' => 'https://instagram.com/one'],
        ['network' => 'instagram', 'url' => 'https://instagram.com/two'],
    ], 'social_links.0.network'],
    'unsupported' => [[['network' => 'other', 'url' => 'https://example.com']], 'social_links.0.network'],
]);

test('social links reject unsafe URLs and domains that do not belong to their network', function (string $url) {
    $owner = User::factory()->withProfile()->create();
    $this->actingAs($owner)->patch(route('member-profile.update'), socialProfilePayload([
        'social_links' => [['network' => 'instagram', 'url' => $url]],
    ]))->assertSessionHasErrors('social_links.0.url');
})->with([
    'http://instagram.com/friend', 'javascript:alert(1)', 'https://instagram.com.evil.test/friend',
    'https://evilinstagram.com/friend', 'https://instagram.com@evil.test/friend',
    'https://name:password@instagram.com/friend', 'https://facebook.com/friend',
    'https://instagram.com:444/friend', 'https://instagram.com\\@evil.test/friend',
    'https://127.0.0.1/friend', 'https://instagram.com./friend', '<a href="https://instagram.com">friend</a>',
]);

test('social visibility is validated and defaults to matches without making links mandatory', function () {
    $owner = User::factory()->withProfile()->create();
    $this->actingAs($owner)->patch(route('member-profile.update'), socialProfilePayload(['social_links_visibility' => 'public']))
        ->assertSessionHasErrors('social_links_visibility');
    $this->patch(route('member-profile.update'), ['display_name' => 'Friend', 'visit_frequency' => 'often', 'visibility' => 'visible'])
        ->assertSessionHasNoErrors();
    expect($owner->profile->fresh()->social_links_visibility?->value)->toBe('matches');
});

test('public and event profile responses enforce social link visibility and blocks', function (string $visibility, bool $matched, bool $blocked, bool $shown) {
    $owner = User::factory()->withProfile()->create();
    $viewer = User::factory()->withProfile()->create();
    $links = socialProfilePayload()['social_links'];
    $owner->profile->update(['social_links' => $links, 'social_links_visibility' => $visibility]);
    if ($matched) {
        MemberMatch::factory()->create(['user_low_id' => min($owner->id, $viewer->id), 'user_high_id' => max($owner->id, $viewer->id)])
            ->conversation()->create();
    }
    if ($blocked) {
        Block::factory()->create(['blocker_user_id' => $owner->id, 'blocked_user_id' => $viewer->id]);
    }
    $event = Event::factory()->create(['organizer_user_id' => $owner->id]);
    EventRegistration::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $viewer->id]);
    foreach ([
        [route('members.show', $owner), 'member.social_links'],
        [route('events.participants.show', [$event, $owner]), 'panel.profile.member.social_links'],
    ] as [$url, $key]) {
        if ($blocked && str_contains($url, '/events/')) {
            $this->actingAs($viewer)->get($url)->assertForbidden();

            continue;
        }
        $this->actingAs($viewer)->get($url)->assertOk()->assertInertia(function (Assert $page) use ($key, $shown, $links) {
            $shown ? $page->where($key, $links) : $page->missing($key);
        });
    }
})->with([
    ['hidden', false, false, false], ['hidden', true, false, false],
    ['matches', false, false, false], ['matches', true, false, true],
    ['members', false, false, true], ['members', true, false, true],
    ['matches', true, true, false], ['members', false, true, false],
]);

test('the owner sees hidden links and exports them while other members cannot access an unavailable profile', function () {
    $owner = User::factory()->withProfile()->create();
    $viewer = User::factory()->withProfile()->create();
    $links = socialProfilePayload()['social_links'];
    $owner->profile->update(['social_links' => $links, 'social_links_visibility' => 'hidden']);
    $this->actingAs($owner)->get(route('member-profile.show'))->assertInertia(fn (Assert $page) => $page
        ->where('profile.social_links', $links)->where('profile.social_links_visibility', 'hidden'));
    $export = app(BuildUserDataExport::class)->handle($owner->fresh());
    expect($export['profile']['social_links'] ?? null)->toEqual($links)
        ->and($export['profile']['social_links_visibility'] ?? null)->toBe('hidden');
    $owner->profile->update(['visibility' => 'hidden', 'social_links_visibility' => 'members']);
    $this->actingAs($viewer)->get(route('members.show', $owner))->assertNotFound();
    $owner->profile->update(['visibility' => 'visible']);
    $owner->forceFill(['status' => UserStatus::PendingDeletion])->save();
    $this->get(route('members.show', $owner))->assertNotFound();
});

test('social links disappear when the account is purged', function () {
    $requestedAt = now()->subDays(31);
    $owner = User::factory()->withProfile()->create(['status' => UserStatus::PendingDeletion, 'deletion_requested_at' => $requestedAt]);
    $owner->profile->update(['social_links' => socialProfilePayload()['social_links']]);
    $profileId = $owner->profile->id;
    (new PurgeDeletedUser($owner->id, $requestedAt->toIso8601String()))->handle();
    $this->assertDatabaseMissing('profiles', ['id' => $profileId]);
});

test('reverse blocks hide links and summaries never expose them', function () {
    $viewer = User::factory()->withProfile()->create();
    $owner = User::factory()->withProfile()->create();
    $owner->profile->update(['social_links_visibility' => 'members', 'social_links' => socialProfilePayload()['social_links']]);
    $event = Event::factory()->create(['organizer_user_id' => $viewer->id]);
    EventRegistration::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $owner->id]);
    $this->actingAs($viewer)->get(route('events.participants.index', $event))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('panel.event.participants', 2)
        ->missing('panel.event.participants.0.social_links')->missing('panel.event.participants.1.social_links'));
    $this->get(route('discovery.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $deferred) => $deferred->has('suggestions', 1)
            ->where('suggestions.0.userId', $owner->id)
            ->missing('suggestions.0.social_links')->missing('suggestions.0.socialLinks')));
    Block::factory()->create(['blocker_user_id' => $viewer->id, 'blocked_user_id' => $owner->id]);
    $this->get(route('members.show', $owner))->assertOk()->assertInertia(fn (Assert $page) => $page->missing('member.social_links'));
    $this->get(route('events.participants.show', [$event, $owner]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('panel.profile.isBlocked', true)->missing('panel.profile.member.social_links'));
});
