<?php

use App\Enums\EventChatSystemType;
use App\Models\Avatar;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\GroupChatMessage;
use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\Trip;
use App\Models\TripRegistration;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Avatar::created(fn (Avatar $avatar) => Storage::put($avatar->image_path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==')));
});

test('the first mobile reaction is reachable by keyboard across shared timelines', function (string $context, int $width) {
    $member = User::factory()->withProfile()->create();
    $peer = User::factory()->withProfile()->create();
    if ($context === 'private') {
        $match = MemberMatch::factory()->create(['user_low_id' => min($member->id, $peer->id), 'user_high_id' => max($member->id, $peer->id)]);
        $conversation = $match->conversation()->create();
        $message = Message::factory()->for($conversation)->for($peer, 'author')->create(['content' => 'Un message reçu']);
        Message::factory()->for($conversation)->for($member, 'author')->create(['content' => 'Mon message']);
        $url = "/conversations/{$conversation->id}";
    } else {
        $group = $context === 'event' ? Event::factory()->for($member, 'organizer')->create() : Trip::factory()->for($member, 'organizer')->create();
        if ($context === 'event') {
            EventRegistration::factory()->accepted()->for($group)->for($peer)->create();
        } else {
            TripRegistration::factory()->for($group)->for($peer)->create(['status' => 'accepted']);
        }
        $chat = $group->chat()->create();
        GroupChatMessage::factory()->for($chat)->for($peer, 'author')->create(['system_type' => EventChatSystemType::Joined, 'content' => '']);
        $message = GroupChatMessage::factory()->for($chat)->for($peer, 'author')->create(['content' => 'Un message reçu']);
        GroupChatMessage::factory()->for($chat)->for($member, 'author')->create(['content' => 'Mon message']);
        $url = $context === 'event' ? "/events/{$group->id}/chat" : "/trips/{$group->id}/chat";
    }
    $this->actingAs($member);
    $page = visit($url, ['viewport' => ['width' => $width, 'height' => 700]])
        ->assertSee('Un message reçu')->waitForEvent('networkidle')
        ->assertScript("document.querySelectorAll('[data-test=message-like]').length", 1)
        ->assertPresent('[data-test="message-like"][aria-label="J’aime ce message"][aria-pressed="false"]')
        ->assertScript("getComputedStyle(document.querySelector('[data-test=message-like]')).display !== 'none'", true)
        ->assertScript("getComputedStyle(document.querySelector('[data-test=message-like]')).clipPath", 'inset(50%)');

    // Positionner le clavier avant le contrôle, puis utiliser une vraie touche Tab.
    $page->script(<<<'JS'
        const bubble = document.querySelector('[data-test=message-bubble]');
        bubble.tabIndex = -1;
        bubble.focus();
        true;
    JS);
    $page->keys('[data-test=message-bubble][tabindex="-1"]', 'Tab')
        ->assertScript("document.activeElement.matches('[data-test=message-like]')", true)
        ->assertScript('document.activeElement.getBoundingClientRect().width > 20 && document.activeElement.getBoundingClientRect().height >= 32', true);
    $page->script(<<<'JS'
        window.reactionOriginalFetch = window.fetch;
        window.fetch = (...args) => String(args[0]).endsWith('/like')
            ? new Promise(resolve => { window.finishReaction = () => resolve(window.reactionOriginalFetch(...args)); })
            : window.reactionOriginalFetch(...args);
        true;
    JS);
    $page->keys('[data-test=message-like]', 'Enter')
        ->assertScript("document.querySelector('[data-test=message-like]').disabled", true);
    $page->script('window.finishReaction(); true;');
    $page->assertPresent('[data-test="message-like"][aria-pressed="true"][aria-label="Retirer mon J’aime"]')
        ->assertSeeIn('[data-test="message-like"]', '1')
        ->assertScript("document.querySelector('[data-test=message-like]').disabled", false);
    $page->script('window.fetch = (...args) => window.reactionOriginalFetch(...args); true;');
    $page->keys('[data-test=message-like]', 'Space')
        ->assertPresent('[data-test="message-like"][aria-pressed="false"]')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
    expect($message->reactions()->count())->toBe(0);
})->with(['private', 'event', 'trip'])->with([320, 390]);

test('a mobile reaction remains disabled in a read only trip', function (string $state, string $locale) {
    $member = User::factory()->withProfile()->create();
    $peer = User::factory()->withProfile()->create();
    $member->update(['locale' => $locale]);
    $trip = Trip::factory()->for($member, 'organizer')->create($state === 'cancelled'
        ? ['cancelled_at' => now()]
        : ['departs_at' => now()->subDays(2)]);
    TripRegistration::factory()->for($trip)->for($peer)->create(['status' => 'accepted']);
    $chat = $trip->chat()->create();
    $message = GroupChatMessage::factory()->for($chat)->for($peer, 'author')->create(['content' => 'Un ancien message']);
    $this->actingAs($member);

    $this->get("/trips/{$trip->id}/chat")->assertOk();
    $page = visit("/trips/{$trip->id}/chat")->on()->mobile()->waitForEvent('networkidle');
    $page->assertNoJavaScriptErrors()->assertSee('Un ancien message')
        ->assertScript("document.querySelector('[data-test=message-like]').disabled", true);
    $page->script(<<<'JS'
        const bubble = document.querySelector('[data-test=message-bubble]');
        bubble.tabIndex = -1;
        bubble.focus();
        bubble.dispatchEvent(new MouseEvent('dblclick', {bubbles: true, cancelable: true}));
        true;
    JS);
    $page->keys('[data-test=message-bubble][tabindex="-1"]', 'Tab')
        ->assertScript("document.activeElement.matches('[data-test=message-like]')", false)
        ->waitForEvent('networkidle')->assertNoJavaScriptErrors();
    expect($message->reactions()->count())->toBe(0);
})->with(['cancelled', 'expired'])->with(['fr', 'en']);

test('expired event discussions remain readable and allow reactions', function (string $locale) {
    $member = User::factory()->withProfile()->create(['locale' => $locale]);
    $peer = User::factory()->withProfile()->create();
    $event = Event::factory()->for($member, 'organizer')->create(['starts_at' => now()->subDays(8)]);
    EventRegistration::factory()->accepted()->for($event)->for($peer)->create();
    $chat = $event->chat()->create();
    $message = GroupChatMessage::factory()->for($chat)->for($peer, 'author')->create(['content' => 'Un message ancien']);
    $this->actingAs($member);

    visit("/events/{$event->id}/chat")->on()->desktop()
        ->assertSee('Un message ancien')
        ->assertSee($locale === 'fr' ? 'Cette discussion est archivée et reste consultable.' : 'This discussion is archived and remains available.')
        ->click('[data-test="message-like"]')
        ->assertPresent('[data-test="message-like"][aria-pressed="true"]')
        ->assertNoJavaScriptErrors();
    expect($message->reactions()->where('user_id', $member->id)->exists())->toBeTrue();
})->with(['fr', 'en']);
