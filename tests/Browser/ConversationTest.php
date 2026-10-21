<?php

use App\Actions\MarkConversationRead;
use App\Actions\SendMessage;
use App\Events\MatchCreated;
use App\Events\MessageSent;
use App\Models\ConversationStarter;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\GroupChat;
use App\Models\GroupChatMessage;
use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\SeasonalTheme;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
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
        ->assertPresent('[aria-label="Conversations"][aria-current="page"]')
        ->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth', true)
        ->assertNoJavaScriptErrors();
});

test('an empty conversation offers three starters that fill an editable draft', function () {
    $member = conversationBrowserMember('Alice');
    $peer = conversationBrowserMember('Basile');
    $match = MemberMatch::factory()->create([
        'user_low_id' => min($member->id, $peer->id),
        'user_high_id' => max($member->id, $peer->id),
    ]);
    $conversation = $match->conversation()->create();
    ConversationStarter::factory()->count(15)->create();
    $this->actingAs($member);

    $page = visit("/conversations/{$conversation->id}")->on()->mobile()
        ->assertScript("document.querySelectorAll('[data-test=conversation-starters] button').length", 3)
        ->assertScript("getComputedStyle(document.querySelector('[data-test=conversation-starters] > div')).flexDirection", 'column');

    $selected = $page->script("document.querySelector('[data-test=conversation-starters] button').textContent.trim()");
    $page->script("document.querySelector('[data-test=conversation-starters] button').click(); true;");
    $page->assertScript("document.querySelector('#message-content').value", $selected)
        ->assertScript('document.activeElement.id', 'message-content')
        ->assertNotPresent('[data-test="conversation-starters"]')
        ->assertNoJavaScriptErrors();
});

test('a member can like then unlike a message from the timeline', function () {
    $member = conversationBrowserMember('Alice');
    $peer = conversationBrowserMember('Basile');
    $match = MemberMatch::factory()->create([
        'user_low_id' => min($member->id, $peer->id),
        'user_high_id' => max($member->id, $peer->id),
    ]);
    $conversation = $match->conversation()->create();
    Message::factory()->for($conversation)->for($peer, 'author')->create(['content' => 'Une belle journée']);
    $this->actingAs($member);

    $page = visit("/conversations/{$conversation->id}")->on()->desktop()
        ->waitForEvent('networkidle')
        ->assertPresent('[data-test="message-like"][aria-pressed="false"]')
        ->assertScript("getComputedStyle(document.querySelector('[data-test=message-like]')).display !== 'none'", true)
        ->click('[data-test="message-like"]')
        ->assertPresent('[data-test="message-like"][aria-pressed="true"]')
        ->assertSeeIn('[data-test="message-like"]', '1')
        ->click('[data-test="message-like"]')
        ->assertPresent('[data-test="message-like"][aria-pressed="false"]')
        ->assertSeeIn('[data-test="message-like"]', '0')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseCount('message_reactions', 0);
});

test('a delayed refresh does not overwrite a newer message reaction', function (bool $unlike) {
    $member = conversationBrowserMember('Alice');
    $peer = conversationBrowserMember('Basile');
    $match = MemberMatch::factory()->create([
        'user_low_id' => min($member->id, $peer->id),
        'user_high_id' => max($member->id, $peer->id),
    ]);
    $conversation = $match->conversation()->create();
    Message::factory()->for($conversation)->for($peer, 'author')->create(['content' => 'Une belle journée']);
    $this->actingAs($member);
    $page = visit("/conversations/{$conversation->id}")->on()->desktop()->assertSee('Une belle journée')->waitForEvent('networkidle');
    $page->script(<<<'JS'
        window.messagingOriginalSend = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.send = function (...args) {
            const loaded = this.onload;
            this.onload = (...events) => {
                window.finishRefresh = () => loaded.apply(this, events);
            };
            return window.messagingOriginalSend.apply(this, args);
        };
        window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true }));
        true;
        JS);
    $page->assertScript('typeof window.finishRefresh', 'function')
        ->click('[data-test="message-like"]')
        ->assertPresent('[data-test="message-like"][aria-pressed="true"]');
    if ($unlike) {
        $page->click('[data-test="message-like"]')
            ->assertPresent('[data-test="message-like"][aria-pressed="false"]');
    }
    $page->script('XMLHttpRequest.prototype.send = window.messagingOriginalSend; window.finishRefresh(); true;');
    $page->waitForEvent('networkidle')
        ->assertPresent('[data-test="message-like"][aria-pressed="'.($unlike ? 'false' : 'true').'"]')
        ->assertNoJavaScriptErrors();
    $this->assertDatabaseCount('message_reactions', $unlike ? 0 : 1);
})->with(['like' => false, 'like then unlike' => true]);

test('a zero reaction stays hidden on mobile until a double tap likes the message', function () {
    $member = conversationBrowserMember('Alice');
    $peer = conversationBrowserMember('Basile');
    $match = MemberMatch::factory()->create([
        'user_low_id' => min($member->id, $peer->id),
        'user_high_id' => max($member->id, $peer->id),
    ]);
    $conversation = $match->conversation()->create();
    $message = Message::factory()->for($conversation)->for($peer, 'author')->create([
        'content' => 'Une belle journée',
    ]);
    $this->actingAs($member);

    $page = visit("/conversations/{$conversation->id}")->on()->mobile()
        ->assertScript("getComputedStyle(document.querySelector('[data-test=message-like]')).display", 'none');

    $defaultPrevented = $page->script(<<<'JS'
        (() => {
            const message = document.querySelector('[data-test="message-bubble"]');
            message.dispatchEvent(new TouchEvent('touchend', { bubbles: true, cancelable: true }));
            const secondTap = new TouchEvent('touchend', { bubbles: true, cancelable: true });
            message.dispatchEvent(secondTap);

            return secondTap.defaultPrevented;
        })()
    JS);

    expect($defaultPrevented)->toBeTrue();

    $page->assertPresent('[data-test="message-like"][aria-pressed="true"]')
        ->assertSeeIn('[data-test="message-like"]', '1')
        ->assertScript("getComputedStyle(document.querySelector('[data-test=message-like]')).display !== 'none'", true)
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('message_reactions', [
        'message_id' => $message->id,
        'user_id' => $member->id,
    ]);
});

test('a member cannot react to their own message from the conversation', function () {
    $member = conversationBrowserMember('Alice');
    $peer = conversationBrowserMember('Basile');
    $match = MemberMatch::factory()->create([
        'user_low_id' => min($member->id, $peer->id),
        'user_high_id' => max($member->id, $peer->id),
    ]);
    $conversation = $match->conversation()->create();
    Message::factory()->for($conversation)->for($member, 'author')->create([
        'content' => 'Mon propre message',
    ]);
    $this->actingAs($member);

    visit("/conversations/{$conversation->id}")->on()->mobile()
        ->assertMissing('[data-test="message-like"]')
        ->assertNoJavaScriptErrors();
});

test('the conversation list stays within the mobile viewport and opens a peer', function () {
    $member = conversationBrowserMember('Alice');
    $peer = conversationBrowserMember(str_repeat('BasileSansEspace', 5));
    [$lowId, $highId] = collect([$member->id, $peer->id])->sort()->values()->all();
    $match = MemberMatch::factory()->create([
        'user_low_id' => $lowId,
        'user_high_id' => $highId,
    ]);
    $conversation = $match->conversation()->create();
    Message::factory()->for($conversation)->for($peer, 'author')->create([
        'content' => str_repeat('MessageSansEspace', 20),
    ]);
    $this->actingAs($member);

    $page = visit('/conversations')->on()->mobile();
    $page->resize(320, 700);
    $page
        ->assertPresent("a[href='/conversations/{$conversation->id}'][data-unread='true']")
        ->assertPresent('[aria-label="1 message non lu"]')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click("a[href='/conversations/{$conversation->id}']")
        ->assertPathIs("/conversations/{$conversation->id}")
        ->assertNoJavaScriptErrors();
});

test('a new conversation is first and invites the member to start without appearing unread', function () {
    $member = conversationBrowserMember('Alice');
    $olderPeer = conversationBrowserMember('Basile');
    $newPeer = conversationBrowserMember('Camille');
    $olderMatch = MemberMatch::factory()->create([
        'user_low_id' => min($member->id, $olderPeer->id),
        'user_high_id' => max($member->id, $olderPeer->id),
    ]);
    $newMatch = MemberMatch::factory()->create([
        'user_low_id' => min($member->id, $newPeer->id),
        'user_high_id' => max($member->id, $newPeer->id),
    ]);
    $older = $olderMatch->conversation()->create(['created_at' => now()->subDay()]);
    $new = $newMatch->conversation()->create(['created_at' => now()]);
    Message::factory()->for($older)->for($olderPeer, 'author')->create([
        'created_at' => now()->subHour(),
        'read_at' => now(),
    ]);
    $this->actingAs($member);

    visit('/conversations')->on()->mobile()
        ->assertSee('Nouvel échange')
        ->assertSee('Commence la conversation.')
        ->assertPresent("a[href='/conversations/{$new->id}'][data-unread='false'] [data-test='new-conversation-label']")
        ->assertScript("document.querySelector('[aria-label=Échanges] li:first-child a').getAttribute('href')", "/conversations/{$new->id}")
        ->assertNoJavaScriptErrors();
});

test('the conversation list prefixes the member latest message with vous', function () {
    $member = conversationBrowserMember('Alice');
    $peer = conversationBrowserMember('Basile');
    [$lowId, $highId] = collect([$member->id, $peer->id])->sort()->values()->all();
    $match = MemberMatch::factory()->create([
        'user_low_id' => $lowId,
        'user_high_id' => $highId,
    ]);
    $conversation = $match->conversation()->create();
    Message::factory()->for($conversation)->for($member, 'author')->create([
        'content' => 'À tout de suite !',
    ]);
    $this->actingAs($member);

    visit('/conversations')->on()->mobile()
        ->assertSee('Toi : À tout de suite !')
        ->assertPresent("a[href='/conversations/{$conversation->id}'][data-unread='false']")
        ->assertNoJavaScriptErrors();
});

test('a conversation header shows online presence only beside the label', function () {
    $member = conversationBrowserMember('Alice');
    $peer = conversationBrowserMember('Basile');
    $match = MemberMatch::factory()->create([
        'user_low_id' => min($member->id, $peer->id),
        'user_high_id' => max($member->id, $peer->id),
    ]);
    $conversation = $match->conversation()->create();
    Cache::put("presence:user:{$peer->id}", true, now()->addMinute());
    $this->actingAs($member);

    visit("/conversations/{$conversation->id}")->on()->mobile()
        ->assertSee(__('conversations.presence.online'))
        ->assertScript(<<<'JS'
            (() => {
                const header = document.querySelector('[data-test="conversation-page"] > header');

                return header?.querySelectorAll('.bg-emerald-500').length === 1;
            })()
        JS, true)
        ->assertNoJavaScriptErrors();
});

test('the conversation list scrolls independently and clamps message previews to two lines', function () {
    $member = conversationBrowserMember('Alice');

    foreach (range(1, 12) as $index) {
        $peer = conversationBrowserMember("Membre {$index}");
        $match = MemberMatch::factory()->create([
            'user_low_id' => min($member->id, $peer->id),
            'user_high_id' => max($member->id, $peer->id),
        ]);
        $conversation = $match->conversation()->create();
        Message::factory()->for($conversation)->for($peer, 'author')->create([
            'content' => str_repeat("Long aperçu {$index} ", 20),
        ]);
    }
    $this->actingAs($member);

    visit('/conversations')->on()->mobile()
        ->assertScript(<<<'JS'
            (() => {
                const list = document.querySelector('[aria-label="Échanges"]');

                return getComputedStyle(list).overflowY === 'auto'
                    && list.scrollHeight > list.clientHeight;
            })()
        JS, true)
        ->assertScript(<<<'JS'
            (() => {
                const list = document.querySelector('[aria-label="Échanges"]');
                list.scrollTop = list.scrollHeight;

                return list.scrollTop > 0;
            })()
        JS, true)
        ->assertScript(<<<'JS'
            (() => {
                const preview = document.querySelector('[data-test="conversation-preview"]');
                const style = getComputedStyle(preview);

                return style.webkitLineClamp === '2'
                    && preview.clientHeight <= (parseFloat(style.lineHeight) * 2) + 1;
            })()
        JS, true)
        ->assertNoJavaScriptErrors();
});

test('a member filters conversations locally by member name only', function () {
    $member = conversationBrowserMember('Alice');
    $elodie = conversationBrowserMember('Élodie');
    $basile = conversationBrowserMember('Basile');

    foreach ([$elodie, $basile] as $peer) {
        $match = MemberMatch::factory()->create([
            'user_low_id' => min($member->id, $peer->id),
            'user_high_id' => max($member->id, $peer->id),
        ]);
        $conversation = $match->conversation()->create();
        Message::factory()->for($conversation)->for($peer, 'author')->create([
            'content' => $peer->is($basile) ? 'ELODIE dans le message' : 'Bonjour',
        ]);
    }
    $this->actingAs($member);

    visit('/conversations')->on()->mobile()
        ->type('[data-test="conversation-search"]', 'ELODIE')
        ->assertSee('Élodie')
        ->assertDontSee('Basile')
        ->fill('[data-test="conversation-search"]', 'inconnu')
        ->assertSee('Aucun échange ne correspond à ce nom.')
        ->click('[data-test="clear-conversation-search"]')
        ->assertSee('Élodie')
        ->assertSee('Basile')
        ->assertNoJavaScriptErrors();
});

test('the conversation list updates and reorders its preview in realtime', function () {
    if (! filter_var(env('REALTIME_BROWSER_TESTS', false), FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('Set REALTIME_BROWSER_TESTS=true and start Reverb to run this integration test.');
    }

    $member = conversationBrowserMember('Alice');
    $firstPeer = conversationBrowserMember('Basile');
    $secondPeer = conversationBrowserMember('Camille');
    $firstMatch = MemberMatch::factory()->create([
        'user_low_id' => min($member->id, $firstPeer->id),
        'user_high_id' => max($member->id, $firstPeer->id),
    ]);
    $secondMatch = MemberMatch::factory()->create([
        'user_low_id' => min($member->id, $secondPeer->id),
        'user_high_id' => max($member->id, $secondPeer->id),
    ]);
    $first = $firstMatch->conversation()->create();
    $second = $secondMatch->conversation()->create();
    Message::factory()->for($first)->for($firstPeer, 'author')->create([
        'content' => 'Conversation initiale',
        'read_at' => now(),
        'created_at' => now()->subMinute(),
    ]);
    $this->actingAs($member);

    $page = visit('/conversations')->on()->mobile()
        ->assertSee('Conversation initiale')
        ->assertPresent("a[href='/conversations/{$second->id}'] [data-test='new-conversation-label']");

    $this->app->make(SendMessage::class)->handle($secondPeer, $second, 'Aperçu reçu en direct');

    $page->assertSee('Aperçu reçu en direct')
        ->assertPresent("a[href='/conversations/{$second->id}'][data-unread='true']")
        ->assertNotPresent("a[href='/conversations/{$second->id}'] [data-test='new-conversation-label']")
        ->assertScript("document.querySelector('[aria-label=Échanges] li:first-child a').getAttribute('href')", "/conversations/{$second->id}")
        ->assertNoJavaScriptErrors();

    $this->travel(1)->seconds();
    $this->app->make(SendMessage::class)->handle($member, $first, 'Réponse en direct');

    $page->assertSee('Toi : Réponse en direct')
        ->assertScript("document.querySelector('[aria-label=Échanges] li:first-child a').getAttribute('href')", "/conversations/{$first->id}")
        ->assertNoJavaScriptErrors();
});

test('a conversation created in realtime appears first without a reload', function () {
    if (! filter_var(env('REALTIME_BROWSER_TESTS', false), FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('Set REALTIME_BROWSER_TESTS=true and start Reverb to run this integration test.');
    }

    $member = conversationBrowserMember('Alice');
    $olderPeer = conversationBrowserMember('Basile');
    $newPeer = conversationBrowserMember('Camille');
    $olderMatch = MemberMatch::factory()->create([
        'user_low_id' => min($member->id, $olderPeer->id),
        'user_high_id' => max($member->id, $olderPeer->id),
    ]);
    $older = $olderMatch->conversation()->create(['created_at' => now()->subDay()]);
    Message::factory()->for($older)->for($olderPeer, 'author')->create([
        'created_at' => now()->subHour(),
        'read_at' => now(),
    ]);
    $this->actingAs($member);

    $page = visit('/conversations')->on()->mobile()
        ->assertSee('Basile')
        ->assertDontSee('Camille');

    $newMatch = MemberMatch::factory()->create([
        'user_low_id' => min($member->id, $newPeer->id),
        'user_high_id' => max($member->id, $newPeer->id),
    ]);
    $new = $newMatch->conversation()->create(['created_at' => now()]);
    event(new MatchCreated($newMatch, $member));

    $page->assertSee('Camille')
        ->assertPresent("a[href='/conversations/{$new->id}'] [data-test='new-conversation-label']")
        ->assertScript("document.querySelector('[aria-label=Échanges] li:first-child a').getAttribute('href')", "/conversations/{$new->id}")
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

    foreach (range(1, 65) as $index) {
        Message::factory()->for($conversation)->for($index % 2 === 0 ? $member : $peer, 'author')->create([
            'content' => $index === 65
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
        ->assertScript('window.__messageXss !== true', true);

    $page
        ->assertScript("document.querySelector('[data-test=message-scroll]').scrollTop > 0", true)
        ->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth', true);

    $page->script(<<<'JS'
        const scroll = document.querySelector('[data-test=message-scroll]');
        // Hold only the real history request until the reading position changes.
        const setRequestHeader = XMLHttpRequest.prototype.setRequestHeader;
        const send = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.setRequestHeader = function (name, value) {
            if (name.toLowerCase() === 'x-inertia-infinite-scroll-merge-intent') {
                this.isHistoryRequest = true;
            }
            return setRequestHeader.call(this, name, value);
        };
        XMLHttpRequest.prototype.send = function (...args) {
            if (!this.isHistoryRequest) {
                return send.apply(this, args);
            }
            window.releaseHistoryRequest = () => {
                XMLHttpRequest.prototype.setRequestHeader = setRequestHeader;
                XMLHttpRequest.prototype.send = send;
                send.apply(this, args);
            };
        };
        scroll.scrollTop = 160;
    JS);
    $page->assertScript("typeof window.releaseHistoryRequest === 'function'", true);
    $page->script(<<<'JS'
        const scroll = document.querySelector('[data-test=message-scroll]');
        scroll.scrollTop = 260;
        const anchor = [...scroll.querySelectorAll('[data-test=message-bubble]')]
            .find(message => message.getBoundingClientRect().bottom > scroll.getBoundingClientRect().top);
        window.historyAnchor = { id: anchor.closest('[data-message-id]').dataset.messageId, top: anchor.getBoundingClientRect().top };
        window.releaseHistoryRequest();
    JS);
    $page->assertSee('Message 26')
        ->wait(0.5)
        ->assertScript("Math.abs(document.querySelector('[data-message-id=\"' + window.historyAnchor.id + '\"] [data-test=message-bubble]').getBoundingClientRect().top - window.historyAnchor.top) < 3", true)
        ->assertScript("document.querySelector('[data-test=message-scroll]').scrollTop > 0", true);

    $page->script("document.querySelector('[data-test=message-scroll]').scrollTop = 0; true;");
    $page->assertSee('Message 6')
        ->assertScript("document.querySelector('[data-test=message-scroll]').scrollTop > 0", true);

    $page->script("document.querySelector('[data-test=message-scroll]').scrollTop = 0; true;");
    $page->assertSee('Message 1')
        ->assertScript("document.querySelectorAll('[data-message-id]').length", 65)
        ->assertScript("new Set([...document.querySelectorAll('[data-message-id]')].map((message) => message.dataset.messageId)).size", 65)
        ->assertNoJavaScriptErrors();
});

test('one upward scroll loads one history page and keeps the reading position', function (int $width, int $height, int $scrollTop) {
    $member = conversationBrowserMember('Alice');
    $peer = conversationBrowserMember('Basile');
    $match = MemberMatch::factory()->create([
        'user_low_id' => min($member->id, $peer->id),
        'user_high_id' => max($member->id, $peer->id),
    ]);
    $conversation = $match->conversation()->create();
    foreach (range(1, 120) as $index) {
        Message::factory()->for($conversation)->for($peer, 'author')->create([
            'content' => "Message court {$index}\nUne seconde ligne pour garder l’historique hors du seuil initial.",
            'created_at' => now()->addSeconds($index),
        ]);
    }
    $this->actingAs($member);
    $page = visit("/conversations/{$conversation->id}", ['viewport' => ['width' => $width, 'height' => $height]])
        ->assertSee('Message court 120')
        ->assertScript("document.querySelectorAll('[data-message-id]').length", 20)
        ->waitForEvent('networkidle');
    $page->script("window.historyScrollTarget = {$scrollTop};");
    $page->script(<<<'JS'
        window.loadedBeforeScroll = document.querySelectorAll('[data-message-id]').length;
        window.historyRequests = 0;
        window.originalHistoryHeader = XMLHttpRequest.prototype.setRequestHeader;
        XMLHttpRequest.prototype.setRequestHeader = function (name, value) {
            if (name.toLowerCase() === 'x-inertia-infinite-scroll-merge-intent') window.historyRequests++;
            return window.originalHistoryHeader.call(this, name, value);
        };
        const scroll = document.querySelector('[data-test=message-scroll]');
        scroll.scrollTop = window.historyScrollTarget;
        const anchor = [...scroll.querySelectorAll('[data-test=message-bubble]')]
            .find(bubble => bubble.getBoundingClientRect().bottom > scroll.getBoundingClientRect().top);
        window.topReadingAnchor = { id: anchor.closest('[data-message-id]').dataset.messageId, top: anchor.getBoundingClientRect().top };
        true;
        JS);
    $page->assertScript(<<<'JS'
        window.historyRequests > 0 ? 'requested' : JSON.stringify((() => {
            const scroll = document.querySelector('[data-test=message-scroll]');
            return {
                scrollTop: scroll.scrollTop,
                scrollHeight: scroll.scrollHeight,
                clientHeight: scroll.clientHeight,
                firstItemTop: scroll.querySelector('li').getBoundingClientRect().top,
                containerTop: scroll.getBoundingClientRect().top,
                loaded: document.querySelectorAll('[data-message-id]').length,
                viewport: [innerWidth, innerHeight],
            };
        })())
        JS, 'requested')->waitForEvent('networkidle')
        ->assertScript('window.historyRequests', 1)
        ->assertScript("document.querySelectorAll('[data-message-id]').length - window.loadedBeforeScroll", 20)
        ->assertScript("Math.abs(document.querySelector('[data-message-id=\"' + window.topReadingAnchor.id + '\"] [data-test=message-bubble]').getBoundingClientRect().top - window.topReadingAnchor.top) < 3", true)
        ->assertScript("document.querySelector('[data-test=message-scroll]').scrollTop > 400", true)
        ->assertNoJavaScriptErrors();
})->with([
    'mobile at top' => [390, 844, 0],
    'desktop at top' => [1280, 900, 0],
    'mobile before top' => [390, 844, 350],
    'desktop before top' => [1280, 900, 350],
]);

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

    foreach (range(1, 22) as $index) {
        Message::factory()->for($conversation)->for($peer, 'author')->create([
            'content' => "Historique {$index} ".str_repeat('contenu ', 20),
            'created_at' => now()->addSeconds($index),
        ]);
    }

    $this->actingAs($member);
    $page = visit("/conversations/{$conversation->id}")->on()->mobile()
        ->assertScript("document.querySelectorAll('[data-message-id]').length", 20);

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
        ->assertScript("document.querySelectorAll('[data-message-id]').length", 21)
        ->assertScript("Math.abs(document.querySelector('[data-test=message-scroll]').scrollTop - window.__scrollBeforePushedMessage) < 2", true);

    expect($pushed->fresh()?->read_at)->not->toBeNull();
    expect($member->notifications()->where('data->target_id', $conversation->id)->sole()->read_at)
        ->not->toBeNull();

    event(new MessageSent($pushed));
    $page->assertScript("document.querySelectorAll('[data-message-id]').length", 21)
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

test('a read receipt updates only the last outgoing message without moving the timeline', function () {
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
    Message::factory()->for($conversation)->for($member, 'author')->count(12)->create();
    $this->actingAs($member);

    $page = visit("/conversations/{$conversation->id}")->on()->mobile()
        ->assertNotPresent('[data-test="last-message-read"]');
    $page->script(<<<'JS'
        const scroll = document.querySelector('[data-test=message-scroll]');
        scroll.scrollTop = Math.max(1, Math.floor(scroll.scrollHeight / 3));
        window.__scrollBeforeReadReceipt = scroll.scrollTop;
        true;
    JS);

    $this->app->make(MarkConversationRead::class)->handle($peer, $conversation);

    $page->assertSee('Lu')
        ->assertScript("document.querySelectorAll('[data-test=last-message-read]').length", 1)
        ->assertScript("Math.abs(document.querySelector('[data-test=message-scroll]').scrollTop - window.__scrollBeforeReadReceipt) < 2", true)
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

test('conversation atmosphere stays readable and changes with seasonal themes', function () {
    $member = conversationBrowserMember('Alice');
    $peer = conversationBrowserMember('Basile');
    $match = MemberMatch::factory()->create([
        'user_low_id' => min($member->id, $peer->id),
        'user_high_id' => max($member->id, $peer->id),
    ]);
    $conversation = $match->conversation()->create();
    Message::factory()->for($conversation)->for($peer, 'author')->create([
        'content' => 'Message de la veille',
        'created_at' => now()->subDay(),
    ]);
    Message::factory()->for($conversation)->for($member, 'author')->create([
        'content' => str_repeat('message-sans-espace', 110),
        'created_at' => now(),
    ]);
    $this->actingAs($member);

    $page = visit("/conversations/{$conversation->id}");
    $page->resize(320, 700);

    foreach ([[null, 'standard'], ['halloween', 'halloween'], ['christmas', 'christmas']] as [$theme, $pattern]) {
        SeasonalTheme::query()->update(['is_manually_active' => false]);
        if ($theme !== null) {
            SeasonalTheme::query()->where('theme', $theme)->update([
                'is_manually_active' => true,
            ]);
        }

        $page->navigate("/conversations/{$conversation->id}")
            ->assertPresent("[data-test='conversation-pattern'][data-pattern='{$pattern}']")
            ->assertSee('Basile')
            ->assertSee('Vous')
            ->assertSee('Hier')
            ->assertSee('Aujourd’hui')
            ->assertCount('[data-test="conversation-day-separator"]', 2)
            ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
            ->assertNoJavaScriptErrors();
    }
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
        ->assertSee('Cet échange est archivé. Tu ne peux plus envoyer de nouveaux messages.')
        ->assertNotPresent('textarea[name="content"]')
        ->assertNotPresent('[data-test="member-bottom-navigation"]')
        ->assertNoJavaScriptErrors();
});

test('messaging switches between private and event groups and sends in the shared group chat', function () {
    $member = conversationBrowserMember('Alice');
    $peer = conversationBrowserMember('Basile');
    $match = MemberMatch::factory()->create([
        'user_low_id' => min($member->id, $peer->id),
        'user_high_id' => max($member->id, $peer->id),
    ]);
    $conversation = $match->conversation()->create();
    Message::factory()->count(2)->for($conversation)->for($peer, 'author')->create();
    Message::factory()->for($conversation)->for($member, 'author')->create();
    $event = Event::factory()->for($member, 'organizer')->create(['title' => 'Le groupe du parc']);
    $chat = GroupChat::factory()->for($event)->create();
    EventRegistration::factory()->for($event)->for($peer)->accepted()->create();
    GroupChatMessage::factory()->for($chat)->for($peer, 'author')->create(['content' => 'Bienvenue dans le groupe']);
    $this->actingAs($member);

    $page = visit('/conversations')->resize(390, 700)
        ->assertSee('Basile')->assertDontSee('Le groupe du parc')
        ->assertSeeIn('[data-test=group-unread-count]', '1')
        ->assertSeeIn('[data-test=private-unread-count]', '2');
    GroupChatMessage::factory()->for($chat)->for($peer, 'author')->create(['content' => 'Un nouveau souvenir']);
    $page->click('[data-test="conversations-groups"]')
        ->assertSee('Le groupe du parc')->assertDontSee('Basile')
        ->assertPresent('[aria-label="2 messages non lus"]')
        ->click("[data-test=group-conversation-{$chat->id}]")
        ->assertPresent('[data-test="group-chat-back"]')
        ->assertSee('Bienvenue dans le groupe')
        ->click('[data-test="group-chat-back"]')
        ->assertPathIs('/conversations')
        ->assertAttribute('[data-test="conversations-groups"]', 'aria-selected', 'true')
        ->click("[data-test=group-conversation-{$chat->id}]")
        ->assertPresent('[data-test="conversation-day-separator"]')
        ->fill('#message-content', 'À bientôt au parc')
        ->click('[data-test="group-chat-info"]')
        ->assertSeeIn('[data-test="event-detail"]', 'Le groupe du parc')
        ->assertDontSeeIn('[data-test="event-panel"]', 'Écrire un message')
        ->click('[data-test="participant-stack-trigger"]')
        ->click("[data-test=participant-link-{$peer->id}]")
        ->assertSeeIn('[data-test="event-panel"]', 'Basile')
        ->click('[data-test="participant-profile-back"]')
        ->assertPresent('[data-test="participant-list"]')
        ->keys('[data-test="event-panel"]', 'Escape')
        ->assertMissing('[data-test="event-panel"]')
        ->assertPathIs("/events/{$event->id}/chat")
        ->assertValue('#message-content', 'À bientôt au parc')
        ->click('[aria-label="Envoyer le message"]')
        ->assertSee('À bientôt au parc')
        ->click('[data-test="group-chat-back"]')
        ->assertPresent('[data-test="participant-list"]')
        ->forward()->assertMissing('[data-test="event-panel"]')
        ->assertSee('À bientôt au parc')
        ->navigate('/conversations?category=groups')
        ->wait(0.4)
        ->assertNoJavaScriptErrors()
        ->assertNotPresent('[data-test=group-unread-count]')
        ->assertAttribute('[data-test="conversations-groups"]', 'aria-selected', 'true')
        ->assertAttribute("[data-test=group-conversation-{$chat->id}]", 'data-unread', 'false')
        ->click('[data-test="conversations-private"]')
        ->assertSee('Basile')
        ->click("a[href='/conversations/{$conversation->id}']")
        ->assertPresent('#message-content')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('group_chat_messages', ['group_chat_id' => $chat->id, 'content' => 'À bientôt au parc']);
});

test('messaging categories support keyboard navigation on mobile and desktop', function (int $width, int $height) {
    $this->actingAs(conversationBrowserMember('Alice'));

    visit('/conversations')->resize($width, $height)
        ->keys('[data-test="conversations-private"]', 'ArrowRight')
        ->assertAttribute('[data-test="conversations-groups"]', 'aria-selected', 'true')
        ->keys('[data-test="conversations-groups"]', 'ArrowLeft')
        ->assertAttribute('[data-test="conversations-private"]', 'aria-selected', 'true')
        ->assertNoJavaScriptErrors();
})->with(['mobile' => [390, 700], 'desktop' => [1280, 800]]);

test('resuming the messaging list refreshes previews and unread counts without realtime', function () {
    $member = conversationBrowserMember('Alice');
    $peer = conversationBrowserMember('Basile');
    $match = MemberMatch::factory()->create([
        'user_low_id' => min($member->id, $peer->id),
        'user_high_id' => max($member->id, $peer->id),
    ]);
    $conversation = $match->conversation()->create();
    $this->actingAs($member);
    $page = visit('/conversations')->assertSee('Basile');

    Message::factory()->for($conversation)->for($peer, 'author')->create(['content' => 'Message pendant la veille']);
    $page->script("window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true })); true;");
    $page->assertSee('Message pendant la veille')
        ->assertPresent('[data-test="private-unread-count"]')
        ->assertNoJavaScriptErrors();
});

test('resuming a conversation catches up beyond one page without duplicate messages', function () {
    $member = conversationBrowserMember('Alice');
    $peer = conversationBrowserMember('Basile');
    $match = MemberMatch::factory()->create([
        'user_low_id' => min($member->id, $peer->id),
        'user_high_id' => max($member->id, $peer->id),
    ]);
    $conversation = $match->conversation()->create();
    Message::factory()->for($conversation)->for($peer, 'author')->create(['content' => 'Avant la veille']);
    $this->actingAs($member);
    $page = visit('/conversations')->click("a[href='/conversations/{$conversation->id}']")->assertSee('Avant la veille')->waitForEvent('networkidle');
    foreach (range(1, 25) as $index) {
        Message::factory()->for($conversation)->for($peer, 'author')->create(['content' => "Pendant la veille {$index} ".str_repeat('Un long message pour garder une page complète. ', 12)]);
    }
    $page->fill('content', 'Ma réponse après la veille')->keys('content', 'Enter')->assertSee('Ma réponse après la veille')->assertValue('content', '');
    $page->script("window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true })); true;");
    $page->assertSee('Pendant la veille 25')
        ->assertScript("document.querySelectorAll('[data-message-id]').length", 27)
        ->assertScript("new Set(Array.from(document.querySelectorAll('[data-message-id]'), el => el.dataset.messageId)).size", 27);
    $page->script("window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true })); true;");
    $page->waitForEvent('networkidle')->click('a[href="/conversations"]')->assertPathIs('/conversations')->assertSee('Ma réponse après la veille');
    $page->forward()->assertSee('Basile')
        ->assertScript("document.querySelectorAll('[data-message-id]').length", 27)
        ->assertNoJavaScriptErrors();
});

test('opening the keyboard keeps the latest bubble visible and lets the member return from history', function () {
    $member = conversationBrowserMember('Alice');
    $peer = conversationBrowserMember('Basile');
    $match = MemberMatch::factory()->create([
        'user_low_id' => min($member->id, $peer->id),
        'user_high_id' => max($member->id, $peer->id),
    ]);
    $conversation = $match->conversation()->create();
    Message::factory()->count(10)->for($conversation)->for($peer, 'author')->create(['content' => str_repeat('Un message à lire tranquillement. ', 8)]);
    $this->actingAs($member);
    $page = visit("/conversations/{$conversation->id}")->on()->mobile()->assertSee('Basile');
    $page->assertScript("(() => { const el = document.querySelector('[data-test=message-scroll]'); return el.scrollHeight - el.scrollTop - el.clientHeight < 2; })()", true);
    $page->script(<<<'JS'
        Object.defineProperty(window.visualViewport, 'height', { configurable: true, value: 420 });
        window.visualViewport.dispatchEvent(new Event('resize'));
        true;
        JS);
    $page->script('async () => { await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))); return true; }');
    $page->assertScript("document.querySelector('#message-content').getBoundingClientRect().bottom <= 420", true)
        ->assertScript("(() => { const el = document.querySelector('[data-test=message-scroll]'); return el.scrollHeight - el.scrollTop - el.clientHeight < 2; })()", true);
    $page->script("document.querySelector('[data-test=message-scroll]').scrollTop -= 150; true;");
    $page->assertPresent('[data-test="latest-messages"]');
    $page->script(<<<'JS'
        window.readingPosition = document.querySelector('[data-test=message-scroll]').scrollTop;
        Object.defineProperty(window.visualViewport, 'height', { configurable: true, value: 360 });
        window.visualViewport.dispatchEvent(new Event('resize'));
        true;
        JS);
    $page->script('async () => { await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))); return true; }');
    $page->assertScript("Math.abs(document.querySelector('[data-test=message-scroll]').scrollTop - window.readingPosition) < 2", true)
        ->screenshot(filename: 'messaging-280-reading')
        ->click('[data-test="latest-messages"]')
        ->assertScript("(() => { const el = document.querySelector('[data-test=message-scroll]'); return el.scrollHeight - el.scrollTop - el.clientHeight < 2; })()", true)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'messaging-280-composer');
});

test('enter during composition keeps the draft and starters stay hidden after sending', function () {
    $member = conversationBrowserMember('Alice');
    $peer = conversationBrowserMember('Basile');
    $match = MemberMatch::factory()->create([
        'user_low_id' => min($member->id, $peer->id),
        'user_high_id' => max($member->id, $peer->id),
    ]);
    $conversation = $match->conversation()->create();
    ConversationStarter::factory()->count(3)->create();
    $this->actingAs($member);
    $page = visit("/conversations/{$conversation->id}")->on()->mobile()->fill('content', 'Bonjour Basile');
    $page->script("document.querySelector('#message-content').dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', isComposing: true, bubbles: true, cancelable: true })); true;");
    $page->assertScript("document.querySelector('#message-content').value", 'Bonjour Basile');
    expect($conversation->messages()->count())->toBe(0);
    $page->keys('content', 'Enter')->assertSee('Bonjour Basile')
        ->assertScript("document.querySelector('#message-content').value", '')
        ->assertNotPresent('[data-test="conversation-starters"]')
        ->assertScript('document.activeElement.id', 'message-content')
        ->assertNoJavaScriptErrors();
});

test('browser back refreshes the reply preview and clears the read conversation badge', function () {
    $member = conversationBrowserMember('Alice');
    $peer = conversationBrowserMember('Basile');
    $match = MemberMatch::factory()->create([
        'user_low_id' => min($member->id, $peer->id),
        'user_high_id' => max($member->id, $peer->id),
    ]);
    $conversation = $match->conversation()->create();
    Message::factory()->for($conversation)->for($peer, 'author')->create(['content' => 'On se retrouve demain ?']);
    $this->actingAs($member);
    $page = visit('/conversations')->assertSee('On se retrouve demain ?')
        ->assertPresent('[data-test="private-unread-count"]')
        ->click("a[href='/conversations/{$conversation->id}']")
        ->assertPresent('textarea[name="content"]')
        ->fill('content', 'Avec plaisir, à demain !')
        ->keys('content', 'Enter')
        ->assertSee('Avec plaisir, à demain !')
        ->assertValue('content', '');
    $page->back()->assertSee('Toi : Avec plaisir, à demain !')
        ->assertNotPresent('[data-test="private-unread-count"]')
        ->assertNoJavaScriptErrors();
});

test('a slow failed send preserves textarea focus and the draft for retry', function () {
    $member = conversationBrowserMember('Alice');
    $peer = conversationBrowserMember('Basile');
    $match = MemberMatch::factory()->create([
        'user_low_id' => min($member->id, $peer->id),
        'user_high_id' => max($member->id, $peer->id),
    ]);
    $conversation = $match->conversation()->create();
    $this->actingAs($member);
    $page = visit("/conversations/{$conversation->id}")->on()->mobile()->fill('content', 'Mon brouillon');
    $page->script(<<<'JS'
        window.messagingOriginalFetch = window.fetch.bind(window);
        window.fetch = (url, options) => options?.method === 'POST' && String(url).endsWith('/messages')
            ? new Promise(resolve => { window.finishSend = () => resolve(new Response('{}', { status: 503 })); })
            : window.messagingOriginalFetch(url, options);
        true;
        JS);
    $page->click('button[aria-label="Envoyer le message"]')
        ->assertScript('document.activeElement.id', 'message-content')
        ->assertScript("document.querySelector('#message-content').disabled", false)
        ->assertScript("document.querySelector('#message-content').readOnly", false);
    $page->script('window.finishSend(); true;');
    $page->assertSee('Ton message n’a pas pu être envoyé. Réessaie.')
        ->assertValue('content', 'Mon brouillon')
        ->assertScript('document.activeElement.id', 'message-content')
        ->assertEnabled('button[aria-label="Envoyer le message"]')
        ->assertNoJavaScriptErrors();
    $page->script(<<<'JS'
        window.fetch = (url, options) => options?.method === 'POST' && String(url).endsWith('/messages')
            ? new Promise(resolve => { window.finishSend = () => resolve(window.messagingOriginalFetch(url, options)); })
            : window.messagingOriginalFetch(url, options);
        true;
        JS);
    $page->click('button[aria-label="Envoyer le message"]')
        ->fill('content', 'Le message suivant');
    $page->script('window.finishSend(); true;');
    $page->assertSee('Mon brouillon')
        ->assertValue('content', 'Le message suivant')
        ->assertScript('document.activeElement.id', 'message-content')
        ->assertNoJavaScriptErrors();
    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'content' => 'Mon brouillon',
    ]);
});
