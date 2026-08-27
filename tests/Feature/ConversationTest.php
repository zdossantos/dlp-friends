<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

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
