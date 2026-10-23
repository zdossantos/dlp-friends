<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventChatSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_chat_storage_is_available(): void
    {
        $this->assertTrue(Schema::hasColumns('group_chats', [
            'id',
            'event_id',
            'created_at',
            'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('group_chat_messages', [
            'id',
            'group_chat_id',
            'author_user_id',
            'content',
            'created_at',
            'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('group_chat_reads', [
            'id',
            'group_chat_id',
            'user_id',
            'last_read_message_id',
            'created_at',
            'updated_at',
        ]));
    }
}
