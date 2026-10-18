<?php

use App\Enums\EventRegistrationStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Event;
use App\Models\EventChat;
use App\Models\EventChatMessage;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('messaging lists only groups the member may read and shares their unread state', function () {
    $member = User::factory()->withProfile()->create();
    $organizer = User::factory()->withProfile()->create();
    $event = Event::factory()->for($organizer, 'organizer')->create();
    $chat = EventChat::factory()->for($event)->create();
    EventRegistration::factory()->for($event)->for($member)->create(['status' => EventRegistrationStatus::Accepted]);
    EventChatMessage::factory()->for($chat)->for($organizer, 'author')->create();
    $other = EventChat::factory()->create();

    $this->actingAs($member)->get(route('conversations.index'))
        ->assertInertia(fn (Assert $page) => $page->component('Conversations/Index')
            ->has('groups', 1)->where('groups.0.id', $chat->id)
            ->where('groups.0.unread_count', 1));
    $this->get(route('events.chat.show', ['event' => $event, 'origin' => 'conversations']))
        ->assertInertia(fn (Assert $page) => $page->component('Conversations/Group')
            ->where('chat.id', $chat->id)->has('messages.data', 1));
    $this->postJson(route('events.chat.read.store', $event), ['last_read_message_id' => $chat->messages()->first()->id])->assertSuccessful();
    $this->get(route('conversations.index'))->assertInertia(fn (Assert $page) => $page->where('groups.0.unread_count', 0));
    $this->get(route('events.chat.show', ['event' => $other->event_id, 'origin' => 'conversations']))->assertForbidden();
    $event->registrations()->update(['status' => EventRegistrationStatus::Removed]);
    $this->get(route('conversations.index'))->assertInertia(fn (Assert $page) => $page->has('groups', 0));
    $this->get(route('events.chat.show', ['event' => $event, 'origin' => 'conversations']))->assertForbidden();
});

test('messaging group refresh includes read only state after cancellation', function () {
    $member = User::factory()->withProfile()->create();
    $event = Event::factory()->for($member, 'organizer')->create();
    $chat = EventChat::factory()->for($event)->create();
    $event->update(['cancelled_at' => now()]);

    $this->actingAs($member)->get(route('events.chat.show', ['event' => $event, 'origin' => 'conversations']), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Conversations/Group',
        'X-Inertia-Partial-Data' => 'messages,chat',
    ])->assertOk()
        ->assertJsonPath('component', 'Conversations/Group')
        ->assertJsonPath('props.chat.id', $chat->id)
        ->assertJsonPath('props.chat.isReadOnly', true)
        ->assertJsonPath('props.chat.readOnlyReason', 'cancelled')
        ->assertJsonCount(0, 'props.messages.data');
});
