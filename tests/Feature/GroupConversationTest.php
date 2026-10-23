<?php

use App\Enums\EventRegistrationStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\GroupChat;
use App\Models\GroupChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('messaging lists only groups the member may read and shares their unread state', function () {
    $member = User::factory()->withProfile()->create();
    $organizer = User::factory()->withProfile()->create();
    $event = Event::factory()->for($organizer, 'organizer')->create();
    $chat = GroupChat::factory()->for($event)->create();
    EventRegistration::factory()->for($event)->for($member)->create(['status' => EventRegistrationStatus::Accepted]);
    GroupChatMessage::factory()->for($chat)->for($organizer, 'author')->create();
    $other = GroupChat::factory()->create();

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
    $chat = GroupChat::factory()->for($event)->create();
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

test('event details and participant profiles share the conversation workspace', function () {
    $member = User::factory()->withProfile()->create();
    $peer = User::factory()->withProfile()->create();
    $event = Event::factory()->for($member, 'organizer')->create();
    $chat = GroupChat::factory()->for($event)->create();
    EventRegistration::factory()->for($event)->for($peer)->create(['status' => EventRegistrationStatus::Accepted]);
    $this->actingAs($member);

    $this->get(route('events.chat.show', $event))
        ->assertInertia(fn (Assert $page) => $page->component('Conversations/Group')->where('panel', null));

    foreach ([
        ['events.show', ['event' => $event], 'detail'],
        ['events.participants.index', ['event' => $event], 'participants'],
        ['events.participants.show', ['event' => $event, 'member' => $peer], 'participant-profile'],
    ] as [$route, $parameters, $kind]) {
        $this->get(route($route, $parameters + ['origin' => 'conversations']))
            ->assertInertia(fn (Assert $page) => $page->component('Conversations/Group')
                ->where('event.id', $event->id)->where('chat.id', $chat->id)
                ->where('panel.kind', $kind)->has('messages.data')
                ->where('closeHref', route('events.chat.show', $event, absolute: false)));
    }

    $outsider = User::factory()->withProfile()->create();
    $this->actingAs($outsider)->get(route('events.show', ['event' => $event, 'origin' => 'conversations']))->assertForbidden();
});
test('withdrawing from group details returns to the event without forbidden chat access', function () {
    $member = User::factory()->withProfile()->create();
    $event = Event::factory()->create();
    GroupChat::factory()->for($event)->create();
    EventRegistration::factory()->for($event)->for($member)->create(['status' => EventRegistrationStatus::Accepted]);

    $this->actingAs($member)
        ->from(route('events.show', ['event' => $event, 'origin' => 'conversations']))
        ->delete(route('events.registrations.destroy', ['event' => $event, 'origin' => 'conversations']))
        ->assertRedirect(route('events.show', ['event' => $event, 'origin' => 'mine']));
    $this->get(route('events.show', ['event' => $event, 'origin' => 'mine']))->assertOk();
    $this->get(route('events.chat.show', $event))->assertForbidden();
});
