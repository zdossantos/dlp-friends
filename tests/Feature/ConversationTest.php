<?php

namespace Tests\Feature;

use App\Actions\MarkConversationRead;
use App\Events\MessagesRead;
use App\Models\Block;
use App\Models\Conversation;
use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_members_keep_history_access_but_cannot_send_after_a_block(): void
    {
        [$lowUser, $highUser, $conversation] = $this->conversationMembers();
        Block::factory()->create([
            'blocker_user_id' => $lowUser->id,
            'blocked_user_id' => $highUser->id,
        ]);

        expect(Gate::forUser($lowUser)->allows('view', $conversation))->toBeTrue()
            ->and(Gate::forUser($highUser)->allows('view', $conversation))->toBeTrue()
            ->and(Gate::forUser($lowUser)->allows('send', $conversation))->toBeFalse()
            ->and(Gate::forUser($highUser)->allows('send', $conversation))->toBeFalse();
    }

    public function test_a_match_exposes_its_conversation(): void
    {
        $match = MemberMatch::factory()->create();
        DB::table('conversations')->insert([
            'match_id' => $match->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect($match->conversation)->toBeInstanceOf(Conversation::class)
            ->and($match->conversation?->match_id)->toBe($match->id);
    }

    public function test_both_match_members_can_view_and_send_in_their_conversation(): void
    {
        [$lowUser, $highUser, $conversation] = $this->conversationMembers();

        foreach ([$lowUser, $highUser] as $member) {
            expect(Gate::forUser($member)->allows('view', $conversation))->toBeTrue()
                ->and(Gate::forUser($member)->allows('send', $conversation))->toBeTrue();
        }
    }

    public function test_an_outsider_and_an_unrelated_admin_cannot_access_a_conversation(): void
    {
        [, , $conversation] = $this->conversationMembers();
        $outsider = User::factory()->create();
        $admin = User::factory()->admin()->create();

        foreach ([$outsider, $admin] as $nonMember) {
            expect(Gate::forUser($nonMember)->allows('view', $conversation))->toBeFalse()
                ->and(Gate::forUser($nonMember)->allows('send', $conversation))->toBeFalse();
        }
    }

    public function test_an_archived_conversation_rejects_messages_from_both_members(): void
    {
        [$lowUser, $highUser, $conversation] = $this->conversationMembers();
        $conversation->update(['archived_at' => now()]);

        foreach ([$lowUser, $highUser] as $member) {
            expect(Gate::forUser($member)->allows('view', $conversation))->toBeTrue()
                ->and(Gate::forUser($member)->allows('send', $conversation))->toBeFalse();
        }
    }

    public function test_a_member_initially_receives_only_the_ten_newest_messages_in_chronological_order(): void
    {
        [$member, $peer, $conversation] = $this->conversationMembers(withProfiles: true);
        $messages = Message::factory()->count(15)->sequence(
            fn (Sequence $sequence): array => [
                'author_user_id' => $sequence->index % 2 === 0 ? $member->id : $peer->id,
                'created_at' => now()->addSeconds($sequence->index),
            ],
        )->for($conversation)->create();

        $expectedNewest = $messages->slice(5)->pluck('id')->all();
        $expectedOldest = $messages->take(5)->pluck('id')->all();

        $this->actingAs($member)
            ->get("/conversations/{$conversation->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Conversations/Show')
                ->where('conversation.id', $conversation->id)
                ->where('participant.id', $peer->id)
                ->where('currentUserId', $member->id)
                ->where('messages.data', fn (Collection $rows): bool => $rows->pluck('id')->all() === $expectedNewest)
                ->where('messages.current_page', 2)
                ->has('messages.data', 10));

        $this->actingAs($member)
            ->get("/conversations/{$conversation->id}?messages=1")
            ->assertInertia(fn (Assert $page) => $page
                ->where('messages.data', fn (Collection $rows): bool => $rows->pluck('id')->all() === $expectedOldest)
                ->where('messages.current_page', 1)
                ->has('messages.data', 5));
    }

    public function test_older_history_keeps_its_initial_boundary_when_live_messages_arrive(): void
    {
        [$member, $peer, $conversation] = $this->conversationMembers(withProfiles: true);
        $initialMessages = Message::factory()->count(25)->sequence(
            fn (Sequence $sequence): array => [
                'author_user_id' => $sequence->index % 2 === 0 ? $member->id : $peer->id,
            ],
        )->for($conversation)->create();
        $boundary = $initialMessages->last()->id;

        Message::factory()->for($conversation)->for($peer, 'author')->create();

        $expectedPrevious = $initialMessages->slice(5, 10)->pluck('id')->all();

        $this->actingAs($member)
            ->get("/conversations/{$conversation->id}?messages=2&messages_before={$boundary}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('messages.data', fn (Collection $rows): bool => $rows->pluck('id')->all() === $expectedPrevious)
                ->where('messages.current_page', 2)
                ->where('messages.next_page_url', fn (string $url): bool => str_contains($url, "messages_before={$boundary}"))
                ->has('messages.data', 10));
    }

    public function test_opening_a_conversation_marks_only_received_messages_as_read_and_broadcasts_the_latest_receipt(): void
    {
        [$member, $peer, $conversation] = $this->conversationMembers(withProfiles: true);
        $alreadyRead = Message::factory()->for($conversation)->for($peer, 'author')->create([
            'read_at' => now()->subMinute(),
        ]);
        $received = Message::factory()->for($conversation)->for($peer, 'author')->create();
        $sent = Message::factory()->for($conversation)->for($member, 'author')->create();
        Event::fake([MessagesRead::class]);

        $this->actingAs($member)->get("/conversations/{$conversation->id}")->assertOk();

        expect($alreadyRead->fresh()?->read_at)->not->toBeNull()
            ->and($received->fresh()?->read_at)->not->toBeNull()
            ->and($sent->fresh()?->read_at)->toBeNull();
        Event::assertDispatched(MessagesRead::class, fn (MessagesRead $event): bool => $event->conversationId === $conversation->id
            && $event->readerUserId === $member->id
            && $event->lastReadMessageId === $received->id
        );
    }

    public function test_opening_a_conversation_without_unread_messages_dispatches_no_receipt(): void
    {
        [$member, $peer, $conversation] = $this->conversationMembers(withProfiles: true);
        Message::factory()->for($conversation)->for($peer, 'author')->create(['read_at' => now()]);
        Event::fake([MessagesRead::class]);

        $this->actingAs($member)->get("/conversations/{$conversation->id}")->assertOk();

        Event::assertNotDispatched(MessagesRead::class);
    }

    public function test_a_member_can_mark_a_live_received_message_as_read(): void
    {
        [$member, $peer, $conversation] = $this->conversationMembers(withProfiles: true);
        $received = Message::factory()->for($conversation)->for($peer, 'author')->create();
        Event::fake([MessagesRead::class]);

        $this->actingAs($member)
            ->post("/conversations/{$conversation->id}/read")
            ->assertNoContent();

        expect($received->fresh()?->read_at)->not->toBeNull();
        Event::assertDispatched(MessagesRead::class);
    }

    public function test_an_outsider_cannot_mark_a_conversation_as_read_through_the_action(): void
    {
        [, $peer, $conversation] = $this->conversationMembers();
        $outsider = User::factory()->create();
        $message = Message::factory()->for($conversation)->for($peer, 'author')->create();

        $this->expectException(AuthorizationException::class);

        app(MarkConversationRead::class)->handle($outsider, $conversation);

        expect($message->fresh()?->read_at)->toBeNull();
    }

    public function test_a_guessed_conversation_identifier_grants_no_access_to_an_outsider_or_admin(): void
    {
        [, , $conversation] = $this->conversationMembers();
        $outsider = User::factory()->withProfile()->create();
        $admin = User::factory()->withProfile()->admin()->create();

        $this->actingAs($outsider)
            ->get("/conversations/{$conversation->id}")
            ->assertForbidden();
        $this->actingAs($admin)
            ->get("/conversations/{$conversation->id}")
            ->assertForbidden();
    }

    /** @return array{User, User, Conversation} */
    private function conversationMembers(bool $withProfiles = false): array
    {
        $userFactory = User::factory();

        if ($withProfiles) {
            $userFactory = $userFactory->withProfile();
        }

        $lowUser = $userFactory->create();
        $highUser = $userFactory->create();
        $match = MemberMatch::factory()->create([
            'user_low_id' => $lowUser->id,
            'user_high_id' => $highUser->id,
        ]);
        $conversation = $match->conversation()->create();

        return [$lowUser, $highUser, $conversation];
    }
}
