<?php

namespace Tests\Feature;

use App\Enums\EventRegistrationStatus;
use App\Models\Event;
use App\Models\EventChat;
use App\Models\EventChatMessage;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventChatHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_ten_newest_messages_in_chronological_display_order(): void
    {
        [$event, $chat, $organizer, $participant] = $this->scenario();
        $created = EventChatMessage::factory()->count(12)->for($chat)->for($organizer, 'author')->create();

        $response = $this->actingAs($participant)->get(route('events.chat.show', $event));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Conversations/Group')
            ->where('panel', null)
            ->where('event.id', $event->id)
            ->where('chat.id', $chat->id)
            ->where('chat.isReadOnly', false)
            ->has('messages.data', 10)
            ->where('messages.data.0.id', $created[2]->id)
            ->where('messages.data.9.id', $created[11]->id)
            ->where('messages.data.0.author.display_name', $organizer->profile?->display_name)
            ->where('closeHref', route('events.chat.show', $event, absolute: false)));
    }

    public function test_an_older_page_and_read_only_metadata_remain_available_to_members(): void
    {
        [$event, $chat, $organizer] = $this->scenario();
        $created = EventChatMessage::factory()->count(12)->for($chat)->for($organizer, 'author')->create();
        $event->update(['cancelled_at' => now()]);

        $this->actingAs($organizer)
            ->get(route('events.chat.show', ['event' => $event, 'messages' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('messages.data', 2)
                ->where('messages.data.0.id', $created[0]->id)
                ->where('messages.data.1.id', $created[1]->id)
                ->where('chat.isReadOnly', true)
                ->where('chat.readOnlyReason', 'cancelled'));
    }

    public function test_non_members_cannot_discover_the_chat_or_its_history(): void
    {
        [$event, $chat, $organizer] = $this->scenario();
        EventChatMessage::factory()->for($chat)->for($organizer, 'author')->create();
        $outsider = User::factory()->withProfile()->create();

        $this->actingAs($outsider)
            ->get(route('events.chat.show', $event))
            ->assertForbidden();
    }

    /** @return array{Event, EventChat, User, User} */
    private function scenario(): array
    {
        $organizer = User::factory()->withProfile()->create();
        $participant = User::factory()->withProfile()->create();
        $event = Event::factory()->create(['organizer_user_id' => $organizer->id]);
        $chat = EventChat::factory()->for($event)->create();
        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'status' => EventRegistrationStatus::Accepted,
        ]);

        return [$event, $chat, $organizer, $participant];
    }
}
