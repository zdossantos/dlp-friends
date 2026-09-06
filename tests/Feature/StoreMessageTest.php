<?php

namespace Tests\Feature;

use App\Enums\ProfileVisibility;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\MemberMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StoreMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_either_member_can_send_a_valid_message(): void
    {
        [$lowUser, $highUser, $conversation] = $this->conversationMembers();

        foreach ([$lowUser, $highUser] as $index => $member) {
            $content = "Bonjour {$index}";

            $this->actingAs($member)
                ->postJson(route('conversations.messages.store', $conversation), [
                    'content' => $content,
                ])
                ->assertCreated()
                ->assertJsonPath('data.conversation_id', $conversation->id)
                ->assertJsonPath('data.author_user_id', $member->id)
                ->assertJsonPath('data.content', $content)
                ->assertJsonStructure(['data' => [
                    'id',
                    'conversation_id',
                    'author_user_id',
                    'content',
                    'created_at',
                    'updated_at',
                ]]);
        }

        $this->assertDatabaseCount('messages', 2);
    }

    public function test_a_message_is_persisted_and_broadcast_when_the_recipient_profile_is_hidden(): void
    {
        [$author, $recipient, $conversation] = $this->conversationMembers();
        $recipient->profile?->update(['visibility' => ProfileVisibility::Hidden]);
        Event::fake([MessageSent::class]);

        $this->actingAs($author)
            ->postJson(route('conversations.messages.store', $conversation), [
                'content' => 'Toujours joignable',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'author_user_id' => $author->id,
            'content' => 'Toujours joignable',
        ]);
        Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($recipient): bool {
            return collect($event->broadcastOn())
                ->contains(fn ($channel): bool => $channel->name === "private-App.Models.User.{$recipient->id}");
        });
    }

    #[DataProvider('invalidContentProvider')]
    public function test_invalid_content_is_rejected(mixed $content): void
    {
        [$member, , $conversation] = $this->conversationMembers();

        $this->actingAs($member)
            ->postJson(route('conversations.messages.store', $conversation), [
                'content' => $content,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_missing_content_is_rejected(): void
    {
        [$member, , $conversation] = $this->conversationMembers();

        $this->actingAs($member)
            ->postJson(route('conversations.messages.store', $conversation))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_two_thousand_unicode_characters_are_accepted(): void
    {
        [$member, , $conversation] = $this->conversationMembers();
        $content = str_repeat('é', 2000);

        $this->actingAs($member)
            ->postJson(route('conversations.messages.store', $conversation), [
                'content' => $content,
            ])
            ->assertCreated()
            ->assertJsonPath('data.content', $content);

        $this->assertDatabaseHas('messages', ['content' => $content]);
    }

    public function test_member_html_is_returned_and_stored_only_as_raw_json_text(): void
    {
        [$member, , $conversation] = $this->conversationMembers();
        $content = '<img src=x onerror=alert(1)>';

        $this->actingAs($member)
            ->postJson(route('conversations.messages.store', $conversation), [
                'content' => $content,
            ])
            ->assertCreated()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonPath('data.content', $content);

        $this->assertDatabaseHas('messages', ['content' => $content]);
    }

    public function test_an_outsider_and_unrelated_admin_cannot_send_messages(): void
    {
        [, , $conversation] = $this->conversationMembers();
        $outsider = User::factory()->withProfile()->create();
        $admin = User::factory()->withProfile()->admin()->create();

        foreach ([$outsider, $admin] as $nonMember) {
            $this->actingAs($nonMember)
                ->postJson(route('conversations.messages.store', $conversation), [
                    'content' => 'Interdit',
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_an_archived_conversation_rejects_messages(): void
    {
        [$lowUser, $highUser, $conversation] = $this->conversationMembers();
        $conversation->update(['archived_at' => now()]);

        foreach ([$lowUser, $highUser] as $member) {
            $this->actingAs($member)
                ->postJson(route('conversations.messages.store', $conversation), [
                    'content' => 'Interdit',
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('messages', 0);
    }

    /** @return array<string, array{mixed}> */
    public static function invalidContentProvider(): array
    {
        return [
            'empty' => [''],
            'spaces only' => ['   '],
            'unicode whitespace only' => ["\t\n\u{00A0}"],
            'not a string' => [['message']],
            'over 2,000 characters' => [str_repeat('é', 2001)],
        ];
    }

    /** @return array{User, User, Conversation} */
    private function conversationMembers(): array
    {
        $first = User::factory()->withProfile()->create();
        $second = User::factory()->withProfile()->create();
        [$lowUser, $highUser] = $first->id < $second->id
            ? [$first, $second]
            : [$second, $first];
        $match = MemberMatch::factory()->create([
            'user_low_id' => $lowUser->id,
            'user_high_id' => $highUser->id,
        ]);

        return [$lowUser, $highUser, $match->conversation()->create()];
    }
}
