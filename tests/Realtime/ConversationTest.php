<?php

use App\Actions\MarkConversationRead;
use App\Actions\SendMessage;
use App\Events\MatchCreated;
use App\Events\MessageSent;
use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Pest\Browser\Api\AwaitableWebpage;

function realtimeBrowserMember(string $displayName): User
{
    $user = User::factory()->withProfile()->create();
    $user->profile->update(['display_name' => $displayName]);

    return $user;
}

function awaitRealtimeSubscription(AwaitableWebpage $page, string $channel): void
{
    $page->page()->context()->addInitScript(<<<'JS'
        window.__realtimeFrames = [];
        window.WebSocket = class extends WebSocket {
            constructor(...args) {
                super(...args);
                this.addEventListener('message', ({ data }) => {
                    const frame = JSON.parse(data);
                    // Observe delivery after Echo handlers and Vue's DOM updates have run.
                    requestAnimationFrame(() => window.__realtimeFrames.push(frame));
                });
            }
        };
    JS);

    $page->refresh()->assertScript(
        "window.__realtimeFrames.some(frame => frame.channel === '{$channel}' && frame.event === 'pusher_internal:subscription_succeeded')",
        true,
    );
}

beforeEach(fn () => Storage::fake('local'));

test('the conversation list updates and reorders its preview in realtime', function () {
    if (! filter_var(env('REALTIME_BROWSER_TESTS', false), FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('Set REALTIME_BROWSER_TESTS=true and start Reverb to run this integration test.');
    }

    $member = realtimeBrowserMember('Alice');
    $firstPeer = realtimeBrowserMember('Basile');
    $secondPeer = realtimeBrowserMember('Camille');
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

    awaitRealtimeSubscription($page, "private-App.Models.User.{$member->id}");

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

    $member = realtimeBrowserMember('Alice');
    $olderPeer = realtimeBrowserMember('Basile');
    $newPeer = realtimeBrowserMember('Camille');
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

    awaitRealtimeSubscription($page, "private-App.Models.User.{$member->id}");

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

test('a pushed message is announced without moving a member who is reading older messages', function () {
    if (! filter_var(env('REALTIME_BROWSER_TESTS', false), FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('Set REALTIME_BROWSER_TESTS=true and start Reverb to run this integration test.');
    }

    $member = realtimeBrowserMember('Alice');
    $peer = realtimeBrowserMember('Basile');
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

    awaitRealtimeSubscription($page, "private-conversation.{$conversation->id}");

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
    $page->assertScript(
        "window.__realtimeFrames.filter(frame => frame.channel === 'private-conversation.{$conversation->id}' && frame.event === 'message.sent' && JSON.parse(frame.data).id === {$pushed->id}).length",
        2,
    )->assertScript("document.querySelectorAll('[data-message-id]').length", 21)
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

    $member = realtimeBrowserMember('Alice');
    $peer = realtimeBrowserMember('Basile');
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
    awaitRealtimeSubscription($page, "private-conversation.{$conversation->id}");

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
