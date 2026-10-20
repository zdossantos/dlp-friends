<?php

use App\Actions\RegisterForEvent;
use App\Actions\RemoveEventParticipant;
use App\Actions\SendEventChatMessage;
use App\Actions\WithdrawFromEvent;
use App\Models\Event;
use App\Models\User;

test('group chat renders membership notices separately from messages in both languages', function (string $locale, string $joined, string $left, string $removed) {
    $organizer = User::factory()->withProfile()->create(['locale' => $locale]);
    $member = User::factory()->withProfile()->create();
    $member->profile->update(['display_name' => 'Camille']);
    $event = Event::factory()->for($organizer, 'organizer')->create();
    $event->chat()->create();
    app(RegisterForEvent::class)->handle($member, $event);
    app(WithdrawFromEvent::class)->handle($member, $event);
    $registration = app(RegisterForEvent::class)->handle($member, $event);
    app(RemoveEventParticipant::class)->handle($organizer, $registration);
    app(SendEventChatMessage::class)->handle($organizer, $event->chat, 'À bientôt dans les parcs');
    $this->actingAs($organizer);

    visit(route('events.chat.show', $event, absolute: false))->resize(320, 800)
        ->assertSee($joined)->assertSee($left)->assertSee($removed)
        ->assertPresent('[data-test="message-bubble"]')
        ->assertScript(<<<'JS'
            (() => {
                const notices = [...document.querySelectorAll('[data-test="event-chat-system-message"]')];
                return notices.length === 4 && notices.every(notice => {
                    const item = notice.closest('[data-message-id]');
                    return !item.querySelector('[data-test="message-like"]')
                        && !item.querySelector('[data-test="message-sender"]')
                        && !item.querySelector('[data-test="message-bubble"]');
                }) && document.documentElement.scrollWidth <= window.innerWidth;
            })()
            JS, true)
        ->assertNoJavaScriptErrors();
})->with([
    ['fr', 'Camille a rejoint le groupe.', 'Camille a quitté le groupe.', 'Camille a été retiré du groupe par l’organisateur.'],
    ['en', 'Camille joined the group.', 'Camille left the group.', 'Camille was removed from the group by the organizer.'],
]);
