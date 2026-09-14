<?php

namespace Tests\Unit\Models;

use App\Models\Event;
use App\Models\EventChat;
use App\Models\EventChatMessage;
use App\Models\EventChatRead;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_one_cascading_chat_per_event_with_messages_and_member_read_cursors(): void
    {
        $event = Event::factory()->create();
        $chat = EventChat::factory()->for($event)->create();
        $author = User::factory()->create();
        $message = EventChatMessage::factory()
            ->for($chat)
            ->for($author, 'author')
            ->create();
        $read = EventChatRead::query()->create([
            'event_chat_id' => $chat->id,
            'user_id' => $author->id,
            'last_read_message_id' => $message->id,
        ]);

        $this->assertTrue($event->chat->is($chat));
        $this->assertTrue($chat->event->is($event));
        $this->assertTrue($chat->messages->contains($message));
        $this->assertTrue($chat->reads->contains($read));
        $this->assertTrue($message->author->is($author));
        $this->assertTrue($read->lastReadMessage->is($message));
        $this->assertTrue($author->eventChatReads->contains($read));

        $this->expectException(QueryException::class);
        EventChat::factory()->for($event)->create();
    }

    public function test_deleting_an_event_cascades_to_its_chat_messages_and_reads(): void
    {
        $event = Event::factory()->create();
        $chat = EventChat::factory()->for($event)->create();
        $message = EventChatMessage::factory()->for($chat)->create();
        $read = EventChatRead::query()->create([
            'event_chat_id' => $chat->id,
            'user_id' => User::factory()->create()->id,
            'last_read_message_id' => $message->id,
        ]);

        $event->delete();

        $this->assertNull(EventChat::find($chat->id));
        $this->assertNull(EventChatMessage::find($message->id));
        $this->assertNull(EventChatRead::find($read->id));
    }
}
