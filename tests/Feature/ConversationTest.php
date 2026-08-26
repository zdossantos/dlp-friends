<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\MemberMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

    public function test_both_match_members_can_open_their_conversation(): void
    {
        [$lowUser, $highUser, $conversation] = $this->conversationMembers(withProfiles: true);

        $this->actingAs($lowUser)
            ->get("/conversations/{$conversation->id}")
            ->assertNoContent();
        $this->actingAs($highUser)
            ->get("/conversations/{$conversation->id}")
            ->assertNoContent();
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
