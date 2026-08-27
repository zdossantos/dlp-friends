<?php

use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function conversationBrowserMember(string $displayName): User
{
    $user = User::factory()->withProfile()->create();
    $user->profile?->update(['display_name' => $displayName]);
    Storage::disk('local')->put($user->profile->avatar->image_path, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==',
    ));

    return $user;
}

beforeEach(fn () => Storage::fake('local'));

test('a completed member can open the empty conversation list from mobile navigation', function () {
    $member = conversationBrowserMember('Alice');
    $this->actingAs($member);

    visit('/conversations')->on()->mobile()
        ->assertSee('Mes échanges')
        ->assertSee('Aucun échange pour le moment')
        ->assertPresent('[aria-label="Échanges"][aria-current="page"]')
        ->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth', true)
        ->assertNoJavaScriptErrors();
});

test('the conversation list links to a peer and previews its latest message', function () {
    $member = conversationBrowserMember('Alice');
    $peer = conversationBrowserMember('Basile');
    [$lowId, $highId] = collect([$member->id, $peer->id])->sort()->values()->all();
    $match = MemberMatch::factory()->create([
        'user_low_id' => $lowId,
        'user_high_id' => $highId,
    ]);
    $conversation = $match->conversation()->create();
    Message::factory()->for($conversation)->for($peer, 'author')->create([
        'content' => 'On se retrouve devant le château.',
    ]);
    $this->actingAs($member);

    visit('/conversations')->on()->mobile()
        ->assertSee('Basile')
        ->assertSee('On se retrouve devant le château.')
        ->assertPresent("a[href='/conversations/{$conversation->id}']")
        ->assertNoJavaScriptErrors();
});
