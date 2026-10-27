<?php

use App\Enums\ProfileVisibility;
use App\Enums\SocialLinksVisibility;
use App\Enums\UserStatus;
use App\Models\Block;
use App\Models\MemberMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('loads the existing public profile as json with only authorized social links', function () {
    $viewer = User::factory()->withProfile()->create();
    $member = User::factory()->withProfile()->create();
    $member->profile->update([
        'social_links' => [['network' => 'instagram', 'url' => 'https://www.instagram.com/example/']],
        'social_links_visibility' => SocialLinksVisibility::Matches,
    ]);
    $this->actingAs($viewer)->getJson(route('members.show', $member))
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('member.id', $member->id)
        ->assertJsonPath('isBlocked', false)
        ->assertJsonMissingPath('member.social_links')
        ->assertJsonMissingPath('member.email')->assertJsonMissingPath('member.birth_date');

    MemberMatch::factory()->create([
        'user_low_id' => min($viewer->id, $member->id),
        'user_high_id' => max($viewer->id, $member->id),
    ]);
    $this->getJson(route('members.show', $member))->assertOk()
        ->assertJsonPath('member.social_links.0.url', 'https://www.instagram.com/example/');

    $member->profile->update(['social_links_visibility' => SocialLinksVisibility::Hidden]);
    $this->getJson(route('members.show', $member))->assertOk()->assertJsonMissingPath('member.social_links');
});

it('refuses unavailable drawer profiles without exposing their content', function (string $restriction) {
    $viewer = User::factory()->withProfile()->create();
    $member = User::factory()->withProfile()->create();
    match ($restriction) {
        'hidden' => $member->profile->update(['visibility' => ProfileVisibility::Hidden]),
        'inactive' => $member->forceFill(['status' => UserStatus::PendingDeletion])->save(),
        'unverified' => $member->forceFill(['email_verified_at' => null])->save(),
        'incomplete' => $member->profile->update(['bio' => null, 'onboarding_completed_at' => null]),
        'outgoing-block' => Block::factory()->create(['blocker_user_id' => $viewer->id, 'blocked_user_id' => $member->id]),
        'incoming-block' => Block::factory()->create(['blocker_user_id' => $member->id, 'blocked_user_id' => $viewer->id]),
    };

    $this->actingAs($viewer)->getJson(route('members.show', $member))
        ->assertNotFound()->assertJsonMissingPath('member');
})->with(['hidden', 'inactive', 'unverified', 'incomplete', 'outgoing-block', 'incoming-block']);
