<?php

use App\Models\MemberMatch;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function blockingBrowserMember(string $displayName): User
{
    $user = User::factory()->withProfile()->create();
    $user->profile?->update(['display_name' => $displayName]);
    Storage::disk('local')->put($user->profile->avatar->image_path, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==',
    ));

    return $user;
}

beforeEach(fn () => Storage::fake('local'));

test('a member blocks another member from the public profile', function () {
    $viewer = blockingBrowserMember('Alice');
    $member = blockingBrowserMember('Basile');
    $this->actingAs($viewer);

    visit(route('members.show', $member))->on()->mobile()
        ->assertPresent('[data-test="public-member-profile"]')
        ->click('@block-member-trigger')
        ->assertSee('Bloquer ce membre ?')
        ->click('@confirm-block-member')
        ->assertPathIs('/discover')
        ->assertSee('Ce profil n’est plus accessible.')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('blocks', [
        'blocker_user_id' => $viewer->id,
        'blocked_user_id' => $member->id,
    ]);
});

test('a conversation links to the public profile and exposes the same block action', function () {
    $member = blockingBrowserMember('Alice');
    $peer = blockingBrowserMember('Basile');
    $conversation = MemberMatch::factory()->create([
        'user_low_id' => min($member->id, $peer->id),
        'user_high_id' => max($member->id, $peer->id),
    ])->conversation()->create();
    $this->actingAs($member);

    visit(route('conversations.show', $conversation))->on()->mobile()
        ->assertPresent("a[href='/members/{$peer->id}']")
        ->assertPresent('[data-test="block-member-trigger"]')
        ->assertNoJavaScriptErrors();
});
