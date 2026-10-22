<?php

use App\Actions\RemoveTripParticipant;
use App\Actions\SendGroupChatMessage;
use App\Models\Event;
use App\Models\GroupChatMessage;
use App\Models\Trip;
use App\Models\TripRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->organizer = User::factory()->withProfile()->create();
    $this->member = User::factory()->withProfile()->create();
    $this->trip = Trip::factory()->for($this->organizer, 'organizer')->create(['audience' => 'members']);
    $this->chat = $this->trip->chat()->create();
    $this->registration = TripRegistration::factory()->for($this->trip)->for($this->member)->create(['status' => 'accepted']);
});

test('trip chat paginates only its own messages and tracks each members cursor independently', function () {
    for ($i = 0; $i < 25; $i++) {
        app(SendGroupChatMessage::class)->handle($this->organizer, $this->chat, 'Message '.$i);
    }
    $this->actingAs($this->member)->get('/trips/'.$this->trip->id.'/chat')->assertInertia(fn (Assert $page) => $page->component('Conversations/Group')->has('messages.data', 10)->where('trip.meeting_point', $this->trip->meeting_point)->missing('event'));
    $this->get('/trips/'.$this->trip->id.'/chat?messages=3')->assertInertia(fn (Assert $page) => $page->has('messages.data', 5)->where('messages.data.0.content', 'Message 0'));
    $latest = $this->chat->messages()->latest('id')->first();
    $this->get('/conversations?category=groups')->assertInertia(fn (Assert $page) => $page->where('groups.0.unread_count', 25)->where('groups.0.context', 'trip'));
    $this->postJson('/trips/'.$this->trip->id.'/chat/read', ['last_read_message_id' => $latest->id])->assertNoContent();
    $this->get('/conversations?category=groups')->assertInertia(fn (Assert $page) => $page->where('groups.0.unread_count', 0));
    $this->postJson('/trips/'.$this->trip->id.'/chat/messages', ['content' => 'Bonjour'])->assertCreated()->assertJsonPath('data.group_chat_id', $this->chat->id);
    $this->actingAs($this->organizer)->get('/conversations?category=groups')->assertInertia(fn (Assert $page) => $page->where('groups.0.unread_count', 1));
});

test('removed participants and outsiders cannot read, send, react or mark trip chat read', function () {
    $message = app(SendGroupChatMessage::class)->handle($this->organizer, $this->chat, 'Privé');
    app(RemoveTripParticipant::class)->handle($this->organizer, $this->registration, 'Motif personnel');
    foreach ([$this->member, User::factory()->withProfile()->create()] as $viewer) {
        $this->actingAs($viewer)->get('/trips/'.$this->trip->id.'/chat')->assertForbidden();
        $this->postJson('/trips/'.$this->trip->id.'/chat/messages', ['content' => 'Interdit'])->assertForbidden();
        $this->postJson('/trips/'.$this->trip->id.'/chat/read', ['last_read_message_id' => $message->id])->assertForbidden();
        $this->postJson('/trips/'.$this->trip->id.'/chat/messages/'.$message->id.'/like')->assertForbidden();
        $this->get('/conversations?category=groups')->assertInertia(fn (Assert $page) => $page->has('groups', 0));
    }
});

test('trip endpoints reject messages belonging to an event and readonly writes', function () {
    $event = Event::factory()->for($this->organizer, 'organizer')->create();
    $eventChat = $event->chat()->create();
    $message = app(SendGroupChatMessage::class)->handle($this->organizer, $eventChat, 'Autre groupe');
    $this->actingAs($this->organizer)->postJson('/trips/'.$this->trip->id.'/chat/messages/'.$message->id.'/like')->assertNotFound();
    $this->postJson('/trips/'.$this->trip->id.'/chat/read', ['last_read_message_id' => $message->id])->assertUnprocessable();
    $this->trip->update(['departs_at' => now()->subHours(24)]);
    $this->postJson('/trips/'.$this->trip->id.'/chat/messages', ['content' => 'Trop tard'])->assertForbidden();
    expect(GroupChatMessage::query()->where('content', 'Trop tard')->exists())->toBeFalse();
});
