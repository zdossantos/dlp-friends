<?php

use App\Models\Avatar;
use App\Models\Event;
use App\Models\GroupChatMessage;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Avatar::created(fn (Avatar $avatar) => Storage::put($avatar->image_path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==')));
});

test('group resume catches every missed page and only acknowledges a visible synchronized timeline', function (string $context) {
    $member = User::factory()->withProfile()->create();
    $group = $context === 'events' ? Event::factory()->for($member, 'organizer')->create() : Trip::factory()->for($member, 'organizer')->create();
    $chat = $group->chat()->create();
    GroupChatMessage::factory()->for($chat)->for($member, 'author')->create(['content' => 'Avant la veille']);
    $this->actingAs($member);
    $page = visit('/conversations?category=groups')->assertPresent("@group-conversation-{$chat->id}");
    $page->script(<<<'JS'
        window.crossPageReplacements = 0;
        const replaceState = history.replaceState;
        history.replaceState = function(state, unused, url) {
            if (url && new URL(url, location.href).pathname !== location.pathname) {
                window.crossPageReplacements++;
            }
            return replaceState.call(this, state, unused, url);
        };
        Object.defineProperty(document, 'visibilityState', {configurable:true, get: () => 'hidden'});
        true;
    JS);
    $page->click("@group-conversation-{$chat->id}")->assertSee('Avant la veille')->waitForEvent('networkidle');
    $this->assertDatabaseMissing('group_chat_reads', ['group_chat_id' => $chat->id, 'user_id' => $member->id]);
    $page->fill('#message-content', 'Brouillon conservé');
    foreach (range(1, 205) as $index) {
        GroupChatMessage::factory()->for($chat)->for($member, 'author')->create(['content' => "Pendant la veille {$index}"]);
    }
    $lastId = $chat->messages()->max('id');
    $page->script(<<<'JS'
        window.catchupRequests = 0;
        const send = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.send = function(body) {
            window.catchupRequests++;
            if (window.catchupRequests === 2) {
                const request = this;
                window.finishCatchup = () => send.call(request, body);
                return;
            }
            return send.call(this, body);
        };
        Object.defineProperty(document, 'visibilityState', {configurable:true, get: () => 'visible'});
        document.dispatchEvent(new Event('visibilitychange'));
        true;
    JS);
    $page->assertScript('typeof window.finishCatchup', 'function')
        ->assertScript("document.querySelectorAll('[data-message-id]').length", 111);
    $this->assertDatabaseMissing('group_chat_reads', ['group_chat_id' => $chat->id, 'user_id' => $member->id]);
    $page->back()->assertPresent("@group-conversation-{$chat->id}")->assertScript('location.pathname', '/conversations')->forward();
    $page->assertSee('Pendant la veille 205')
        ->assertScript("document.querySelectorAll('[data-message-id]').length", 206)
        ->assertScript("new Set(Array.from(document.querySelectorAll('[data-message-id]'), el => el.dataset.messageId)).size", 206)
        ->assertValue('#message-content', 'Brouillon conservé')
        // The DOM updates before the read acknowledgement persists Inertia's snapshot.
        ->assertScript('history.state.page.props.messages.data.length', 206)
        ->assertNoJavaScriptErrors();
    $page->back()->assertPresent("@group-conversation-{$chat->id}")->assertScript('location.pathname', '/conversations')->forward()
        ->assertScript("document.querySelectorAll('[data-message-id]').length", 206)
        ->assertValue('#message-content', 'Brouillon conservé');
    $page->assertScript('window.crossPageReplacements', 0);
    $this->assertDatabaseHas('group_chat_reads', ['group_chat_id' => $chat->id, 'user_id' => $member->id, 'last_read_message_id' => $lastId]);
})->with(['events', 'trips']);
