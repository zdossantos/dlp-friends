<?php

namespace Tests\Feature\Admin;

use App\Models\Conversation;
use App\Models\MemberMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OpenMemberConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('inertia.testing.ensure_pages_exist', false);
    }

    public function test_admin_creates_a_classic_match_and_conversation_without_swipes(): void
    {
        $admin = User::factory()->withProfile()->admin()->create();
        $member = User::factory()->withProfile()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.members.conversation.store', $member));

        $match = MemberMatch::query()->firstOrFail();
        $conversation = Conversation::query()->firstOrFail();
        $response->assertRedirect(route('admin.members.index', ['created_conversation' => $conversation->id]));
        expect([$match->user_low_id, $match->user_high_id])->toBe(collect([$admin->id, $member->id])->sort()->values()->all());
        expect($conversation->match_id)->toBe($match->id);
        $this->assertDatabaseCount('swipes', 0);
        $this->actingAs($admin)
            ->get(route('admin.members.index', ['created_conversation' => $conversation->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('createdMatch.displayName', $member->profile?->display_name)
                ->where('createdMatch.conversationHref', route('conversations.show', $conversation, absolute: false)));
    }

    public function test_existing_pair_is_reused_and_opened_directly(): void
    {
        $admin = User::factory()->withProfile()->admin()->create();
        $member = User::factory()->withProfile()->create();
        [$lowId, $highId] = collect([$admin->id, $member->id])->sort()->values()->all();
        $match = MemberMatch::factory()->create(['user_low_id' => $lowId, 'user_high_id' => $highId]);
        $conversation = $match->conversation()->create();

        $this->actingAs($admin)
            ->post(route('admin.members.conversation.store', $member))
            ->assertRedirect(route('conversations.show', $conversation));

        $this->assertDatabaseCount('matches', 1);
        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_admin_cannot_open_a_conversation_with_another_admin(): void
    {
        $admin = User::factory()->withProfile()->admin()->create();
        $target = User::factory()->withProfile()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.members.conversation.store', $target))
            ->assertForbidden();

        $this->assertDatabaseCount('matches', 0);
    }
}
