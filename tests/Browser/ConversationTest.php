<?php

use App\Actions\SendMessage;
use App\Events\MessageSent;
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

test('a conversation renders safe recent history and repeatedly loads older messages without duplicates', function () {
    $member = conversationBrowserMember('Alice');
    $peer = conversationBrowserMember('Basile');
    [$lowId, $highId] = collect([$member->id, $peer->id])->sort()->values()->all();
    $match = MemberMatch::factory()->create([
        'user_low_id' => $lowId,
        'user_high_id' => $highId,
    ]);
    $conversation = $match->conversation()->create();

    foreach (range(1, 35) as $index) {
        Message::factory()->for($conversation)->for($index % 2 === 0 ? $member : $peer, 'author')->create([
            'content' => $index === 35
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
    $page->assertSee('Message 16')
        ->assertScript("document.querySelector('[data-test=message-scroll]').scrollTop > 0", true);

    $page->script("document.querySelector('[data-test=message-scroll]').scrollTop = 0; true;");
    $page->assertSee('Message 6');

    $page->script("document.querySelector('[data-test=message-scroll]').scrollTop = 0; true;");
    $page->assertSee('Message 1')
        ->assertScript("document.querySelectorAll('[data-message-id]').length", 35)
        ->assertScript("new Set([...document.querySelectorAll('[data-message-id]')].map((message) => message.dataset.messageId)).size", 35)
        ->assertNoJavaScriptErrors();
});

test('a pushed message is announced without moving a member who is reading older messages', function () {
    if (! filter_var(env('REALTIME_BROWSER_TESTS', false), FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('Set REALTIME_BROWSER_TESTS=true and start Reverb to run this integration test.');
    }

    $member = conversationBrowserMember('Alice');
    $peer = conversationBrowserMember('Basile');
    [$lowId, $highId] = collect([$member->id, $peer->id])->sort()->values()->all();
    $match = MemberMatch::factory()->create([
        'user_low_id' => $lowId,
        'user_high_id' => $highId,
    ]);
    $conversation = $match->conversation()->create();

    foreach (range(1, 12) as $index) {
        Message::factory()->for($conversation)->for($peer, 'author')->create([
            'content' => "Historique {$index} ".str_repeat('contenu ', 20),
            'created_at' => now()->addSeconds($index),
        ]);
    }

    $this->actingAs($member);
    $page = visit("/conversations/{$conversation->id}")->on()->mobile()
        ->assertScript("document.querySelectorAll('[data-message-id]').length", 10);

    $page->script(<<<'JS'
        const scroll = document.querySelector('[data-test=message-scroll]');
        scroll.scrollTop = Math.max(1, Math.floor(scroll.scrollHeight / 3));
        window.__scrollBeforePushedMessage = scroll.scrollTop;
        true;
    JS);

    $pushed = $this->app->make(SendMessage::class)->handle(
        $peer,
        $conversation,
        'Message reçu en direct',
    );

    $page->assertSee('Message reçu en direct')
        ->assertScript("document.querySelector('[aria-live=polite]').textContent", 'Nouveau message reçu')
        ->assertScript("document.querySelectorAll('[data-message-id]').length", 11)
        ->assertScript("Math.abs(document.querySelector('[data-test=message-scroll]').scrollTop - window.__scrollBeforePushedMessage) < 2", true);

    event(new MessageSent($pushed));
    $page->assertScript("document.querySelectorAll('[data-message-id]').length", 11)
        ->assertNoJavaScriptErrors();

    $page->script(<<<'JS'
        const scroll = document.querySelector('[data-test=message-scroll]');
        scroll.scrollTop = scroll.scrollHeight;
        true;
    JS);
    $this->app->make(SendMessage::class)->handle(
        $peer,
        $conversation,
        'Message reçu en étant en bas',
    );
    $page->assertSee('Message reçu en étant en bas')
        ->assertScript("(() => { const scroll = document.querySelector('[data-test=message-scroll]'); return scroll.scrollHeight - scroll.scrollTop - scroll.clientHeight < 2; })()", true)
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
        ->assertSee('0 / 2 000')
        ->assertPresent('textarea[aria-describedby="message-character-count message-error"][aria-invalid="false"]')
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
