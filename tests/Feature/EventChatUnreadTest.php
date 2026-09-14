<?php

namespace Tests\Feature;

use App\Enums\EventRegistrationStatus;
use App\Models\Event;
use App\Models\EventChat;
use App\Models\EventChatMessage;
use App\Models\EventChatRead;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventChatUnreadTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_advances_only_the_members_cursor_and_counts_only_later_messages_by_others(): void
    {
        [$event, $chat, $organizer, $participant] = $this->scenario();
        EventChatMessage::factory()->for($chat)->for($participant, 'author')->create();
        $other = EventChatMessage::factory()->for($chat)->for($organizer, 'author')->create();

        $this->actingAs($participant)
            ->get(route('events.mine'))
            ->assertInertia(fn ($page) => $page->where('participating.0.chatUnreadCount', 1));

        $this->actingAs($participant)
            ->postJson(route('events.chat.read.store', $event), [
                'last_read_message_id' => $other->id,
            ])
            ->assertNoContent();

        $this->assertSame(
            $other->id,
            EventChatRead::query()->where('user_id', $participant->id)->sole()->last_read_message_id,
        );
        $this->actingAs($participant)
            ->get(route('events.mine'))
            ->assertInertia(fn ($page) => $page->where('participating.0.chatUnreadCount', 0));
    }

    public function test_an_older_cursor_is_ignored_and_a_foreign_chat_message_is_rejected(): void
    {
        [$event, $chat, $organizer, $participant] = $this->scenario();
        $older = EventChatMessage::factory()->for($chat)->for($organizer, 'author')->create();
        $newer = EventChatMessage::factory()->for($chat)->for($organizer, 'author')->create();

        foreach ([$newer->id, $older->id] as $messageId) {
            $this->actingAs($participant)
                ->postJson(route('events.chat.read.store', $event), [
                    'last_read_message_id' => $messageId,
                ])
                ->assertNoContent();
        }

        $this->assertSame(
            $newer->id,
            EventChatRead::query()->where('user_id', $participant->id)->sole()->last_read_message_id,
        );

        [, $foreignChat] = $this->scenario();
        $foreignMessage = EventChatMessage::factory()->for($foreignChat)->create();
        $this->actingAs($participant)
            ->postJson(route('events.chat.read.store', $event), [
                'last_read_message_id' => $foreignMessage->id,
            ])
            ->assertUnprocessable();
    }

    public function test_an_outsider_cannot_mark_a_chat_as_read(): void
    {
        [$event, $chat, $organizer] = $this->scenario();
        $message = EventChatMessage::factory()->for($chat)->for($organizer, 'author')->create();

        $this->actingAs(User::factory()->withProfile()->create())
            ->postJson(route('events.chat.read.store', $event), [
                'last_read_message_id' => $message->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseEmpty('event_chat_reads');
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
