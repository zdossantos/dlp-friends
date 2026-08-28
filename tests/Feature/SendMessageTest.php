<?php

namespace Tests\Feature;

use App\Actions\SendMessage;
use App\Models\Block;
use App\Models\Conversation;
use App\Models\MemberMatch;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_either_match_member_can_persist_a_message(): void
    {
        [$lowUser, $highUser, $conversation] = $this->conversationMembers();

        foreach ([$lowUser, $highUser] as $index => $member) {
            $content = "Bonjour {$index}";
            $message = app(SendMessage::class)->handle($member, $conversation, $content);

            expect($message->content)->toBe($content)
                ->and($message->author->is($member))->toBeTrue()
                ->and($message->conversation->is($conversation))->toBeTrue();
        }

        $this->assertDatabaseCount('messages', 2);
    }

    public function test_an_outsider_cannot_persist_a_message(): void
    {
        [, , $conversation] = $this->conversationMembers();
        $outsider = User::factory()->create();

        $this->assertSendingIsForbidden($outsider, $conversation);
    }

    public function test_an_unrelated_administrator_cannot_persist_a_message(): void
    {
        [, , $conversation] = $this->conversationMembers();
        $admin = User::factory()->admin()->create();

        $this->assertSendingIsForbidden($admin, $conversation);
    }

    public function test_an_archived_conversation_rejects_messages_from_its_members(): void
    {
        [$lowUser, $highUser, $conversation] = $this->conversationMembers();
        $conversation->update(['archived_at' => now()]);

        foreach ([$lowUser, $highUser] as $member) {
            $this->assertSendingIsForbidden($member, $conversation);
        }
    }

    public function test_a_block_in_either_direction_rejects_messages_from_both_members(): void
    {
        [$lowUser, $highUser, $conversation] = $this->conversationMembers();
        Block::factory()->create([
            'blocker_user_id' => $highUser->id,
            'blocked_user_id' => $lowUser->id,
        ]);

        $this->assertSendingIsForbidden($lowUser, $conversation);
        $this->assertSendingIsForbidden($highUser, $conversation);
    }

    /** @return array{User, User, Conversation} */
    private function conversationMembers(): array
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        [$lowUser, $highUser] = $first->id < $second->id
            ? [$first, $second]
            : [$second, $first];
        $match = MemberMatch::factory()->create([
            'user_low_id' => $lowUser->id,
            'user_high_id' => $highUser->id,
        ]);

        return [$lowUser, $highUser, $match->conversation()->create()];
    }

    private function assertSendingIsForbidden(User $user, Conversation $conversation): void
    {
        try {
            app(SendMessage::class)->handle($user, $conversation, 'Interdit');
            $this->fail('Expected message sending to be forbidden.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('messages', 0);
        }
    }
}
