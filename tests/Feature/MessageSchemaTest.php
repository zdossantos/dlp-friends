<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MessageSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_messages_store_their_conversation_author_and_raw_content(): void
    {
        [$author, $conversation] = $this->conversationWithAuthor();

        expect(Schema::hasColumns('messages', [
            'id',
            'conversation_id',
            'author_user_id',
            'content',
            'created_at',
            'updated_at',
        ]))->toBeTrue();

        $message = Message::factory()
            ->for($conversation)
            ->for($author, 'author')
            ->create(['content' => '<script>alert("x")</script>']);

        expect($message->conversation->is($conversation))->toBeTrue()
            ->and($message->author->is($author))->toBeTrue()
            ->and($conversation->messages()->sole()->is($message))->toBeTrue()
            ->and($author->authoredMessages()->sole()->is($message))->toBeTrue()
            ->and($message->content)->toBe('<script>alert("x")</script>');
    }

    public function test_deleting_a_conversation_deletes_its_messages(): void
    {
        [$author, $conversation] = $this->conversationWithAuthor();
        $message = Message::factory()->for($conversation)->for($author, 'author')->create();

        $conversation->delete();

        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    }

    public function test_deleting_an_author_deletes_their_messages(): void
    {
        [$author, $conversation] = $this->conversationWithAuthor();
        $message = Message::factory()->for($conversation)->for($author, 'author')->create();

        $author->delete();

        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    }

    /** @return array{User, Conversation} */
    private function conversationWithAuthor(): array
    {
        $author = User::factory()->create();
        $otherMember = User::factory()->create();
        [$lowId, $highId] = collect([$author->id, $otherMember->id])->sort()->values()->all();
        $match = MemberMatch::factory()->create([
            'user_low_id' => $lowId,
            'user_high_id' => $highId,
        ]);

        return [$author, $match->conversation()->create()];
    }
}
