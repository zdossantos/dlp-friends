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

test('a conversation renders safe recent history and loads older messages at the top', function () {
    $member = conversationBrowserMember('Alice');
    $peer = conversationBrowserMember('Basile');
    [$lowId, $highId] = collect([$member->id, $peer->id])->sort()->values()->all();
    $match = MemberMatch::factory()->create([
        'user_low_id' => $lowId,
        'user_high_id' => $highId,
    ]);
    $conversation = $match->conversation()->create();

    foreach (range(1, 15) as $index) {
        Message::factory()->for($conversation)->for($index % 2 === 0 ? $member : $peer, 'author')->create([
            'content' => $index === 15
                ? '<img src=x onerror=window.__messageXss=true>'
                : "Message {$index} ".str_repeat('contenu ', 12),
            'created_at' => now()->addSeconds($index),
        ]);
    }

    $this->actingAs($member);

    $page = visit("/conversations/{$conversation->id}")->on()->mobile()
        ->assertPresent('[role="log"][aria-label="Historique des messages"]')
        ->assertSee('<img src=x onerror=window.__messageXss=true>')
        ->assertNotPresent('[role="log"] img[src="x"]')
        ->assertScript('window.__messageXss !== true', true)
        ->assertScript("document.querySelector('[data-test=message-scroll]').scrollTop > 0", true)
        ->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth', true);

    $page->script(<<<'JS'
        const scroll = document.querySelector('[data-test=message-scroll]');
        scroll.scrollTop = 0;
    JS);
    $page->assertSee('Message 1')
        ->assertScript("document.querySelectorAll('[data-message-id]').length", 15)
        ->assertNoJavaScriptErrors();
});

test('a member sends a message with enter and keeps composer focus', function () {
    $member = conversationBrowserMember('Alice');
    $peer = conversationBrowserMember('Basile');
    [$lowId, $highId] = collect([$member->id, $peer->id])->sort()->values()->all();
    $match = MemberMatch::factory()->create([
        'user_low_id' => $lowId,
        'user_high_id' => $highId,
    ]);
    $conversation = $match->conversation()->create();
    $this->actingAs($member);

    visit("/conversations/{$conversation->id}")->on()->mobile()
        ->assertNoJavaScriptErrors()
        ->assertNotPresent('[data-test="member-bottom-navigation"]')
        ->assertDisabled('button[aria-label="Envoyer le message"]')
        ->fill('content', 'Bonjour !')
        ->keys('textarea[name="content"]', 'Enter')
        ->assertSee('Bonjour !')
        ->assertValue('content', '')
        ->assertScript('document.activeElement.name === "content"', true)
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'author_user_id' => $member->id,
        'content' => 'Bonjour !',
    ]);
});

test('an archived conversation remains readable without a composer', function () {
    $member = conversationBrowserMember('Alice');
    $peer = conversationBrowserMember('Basile');
    [$lowId, $highId] = collect([$member->id, $peer->id])->sort()->values()->all();
    $match = MemberMatch::factory()->create([
        'user_low_id' => $lowId,
        'user_high_id' => $highId,
    ]);
    $conversation = $match->conversation()->create([
        'archived_at' => now(),
    ]);
    Message::factory()->for($conversation)->for($peer, 'author')->create([
        'content' => 'Souvenir de notre sortie',
    ]);
    $this->actingAs($member);

    visit("/conversations/{$conversation->id}")->on()->mobile()
        ->assertSee('Souvenir de notre sortie')
        ->assertSee('Cet échange est archivé. L’envoi de nouveaux messages est désactivé.')
        ->assertNotPresent('textarea[name="content"]')
        ->assertNotPresent('[data-test="member-bottom-navigation"]')
        ->assertNoJavaScriptErrors();
});
