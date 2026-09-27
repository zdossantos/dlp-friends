<?php

namespace Tests\Feature;

use App\Events\MessageReactionUpdated;
use App\Models\Block;
use App\Models\Conversation;
use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MessageReactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_participant_can_like_and_unlike_a_message_once(): void
    {
        [$author, $reactor, $conversation] = $this->conversation();
        $message = Message::factory()->for($conversation)->for($author, 'author')->create();
        Event::fake([MessageReactionUpdated::class]);

        $this->actingAs($reactor)
            ->postJson(route('conversations.messages.like', [$conversation, $message]))
            ->assertOk()
            ->assertJsonPath('data.message_id', $message->id)
            ->assertJsonPath('data.reaction_count', 1)
            ->assertJsonPath('data.reacted', true);

        $this->actingAs($reactor)
            ->postJson(route('conversations.messages.like', [$conversation, $message]))
            ->assertOk()
            ->assertJsonPath('data.reaction_count', 1);

        $this->assertDatabaseCount('message_reactions', 1);

        $this->actingAs($reactor)
            ->deleteJson(route('conversations.messages.like', [$conversation, $message]))
            ->assertOk()
            ->assertJsonPath('data.reaction_count', 0)
            ->assertJsonPath('data.reacted', false);
        $this->assertDatabaseCount('message_reactions', 0);
        Event::assertDispatched(MessageReactionUpdated::class, fn (MessageReactionUpdated $event): bool => $event->messageId === $message->id
            && $event->reactorUserId === $reactor->id
            && $event->reactionCount === 0
            && $event->reacted === false);
    }

    public function test_reactions_require_the_message_to_belong_to_an_accessible_unblocked_conversation(): void
    {
        [$author, $reactor, $conversation] = $this->conversation();
        $message = Message::factory()->for($conversation)->for($author, 'author')->create();
        $outsider = User::factory()->withProfile()->create();

        $this->actingAs($outsider)
            ->postJson(route('conversations.messages.like', [$conversation, $message]))
            ->assertForbidden();

        Block::factory()->create([
            'blocker_user_id' => $author->id,
            'blocked_user_id' => $reactor->id,
        ]);
        $this->actingAs($reactor)
            ->postJson(route('conversations.messages.like', [$conversation, $message]))
            ->assertForbidden();

        $third = User::factory()->withProfile()->create();
        [$otherLow, $otherHigh] = $author->id < $third->id ? [$author, $third] : [$third, $author];
        $otherConversation = MemberMatch::factory()->create([
            'user_low_id' => $otherLow->id,
            'user_high_id' => $otherHigh->id,
        ])->conversation()->create();
        $this->actingAs($author)
            ->postJson(route('conversations.messages.like', [$otherConversation, $message]))
            ->assertNotFound();
    }

    public function test_reacting_does_not_change_message_content_or_read_state(): void
    {
        [$author, $reactor, $conversation] = $this->conversation();
        $message = Message::factory()->for($conversation)->for($author, 'author')->create([
            'content' => 'Message privé intact',
            'read_at' => null,
        ]);

        $this->actingAs($reactor)
            ->postJson(route('conversations.messages.like', [$conversation, $message]))
            ->assertOk();

        expect($message->fresh())
            ->content->toBe('Message privé intact')
            ->read_at->toBeNull();
    }

    public function test_conversation_messages_expose_the_total_and_current_members_reaction(): void
    {
        [$author, $reactor, $conversation] = $this->conversation();
        $message = Message::factory()->for($conversation)->for($author, 'author')->create();
        $message->reactions()->create(['user_id' => $reactor->id]);
        $message->reactions()->create(['user_id' => $author->id]);

        $this->actingAs($reactor)
            ->get(route('conversations.show', $conversation))
            ->assertInertia(fn (Assert $page) => $page
                ->where('messages.data.0.reaction_count', 2)
                ->where('messages.data.0.reacted_by_current_user', true));
    }

    /** @return array{User, User, Conversation} */
    private function conversation(): array
    {
        $first = User::factory()->withProfile()->create();
        $second = User::factory()->withProfile()->create();
        [$low, $high] = $first->id < $second->id ? [$first, $second] : [$second, $first];
        $match = MemberMatch::factory()->create([
            'user_low_id' => $low->id,
            'user_high_id' => $high->id,
        ]);

        return [$first, $second, $match->conversation()->create()];
    }
}
