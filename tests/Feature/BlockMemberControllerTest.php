<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\MemberMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockMemberControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_idempotently_block_another_member_and_archive_their_conversation(): void
    {
        $blocker = User::factory()->withProfile()->create();
        $blocked = User::factory()->withProfile()->create();
        [$low, $high] = $blocker->id < $blocked->id ? [$blocker, $blocked] : [$blocked, $blocker];
        $conversation = MemberMatch::factory()->create([
            'user_low_id' => $low->id,
            'user_high_id' => $high->id,
        ])->conversation()->create();

        foreach ([1, 2] as $_attempt) {
            $this->actingAs($blocker)->post(route('members.block', $blocked))
                ->assertRedirectToRoute('discovery.index')
                ->assertInertiaFlash('toast', [
                    'type' => 'success',
                    'message' => __('blocking.completed'),
                ]);
        }

        $this->assertDatabaseCount('blocks', 1);
        expect($conversation->fresh()->archived_at)->not->toBeNull();
    }

    public function test_a_member_cannot_block_themselves(): void
    {
        $member = User::factory()->withProfile()->create();

        $this->actingAs($member)->post(route('members.block', $member))->assertNotFound();
        $this->assertDatabaseCount('blocks', 0);
    }

    public function test_a_member_can_unblock_another_member_and_restore_their_conversation(): void
    {
        $blocker = User::factory()->withProfile()->create();
        $blocked = User::factory()->withProfile()->create();
        $block = Block::factory()->create([
            'blocker_user_id' => $blocker->id,
            'blocked_user_id' => $blocked->id,
        ]);
        $conversation = MemberMatch::factory()->create([
            'user_low_id' => min($blocker->id, $blocked->id),
            'user_high_id' => max($blocker->id, $blocked->id),
        ])->conversation()->create(['archived_at' => now()]);

        $this->actingAs($blocker)
            ->delete(route('members.unblock', $blocked))
            ->assertRedirect(route('members.show', $blocked));

        $this->assertDatabaseMissing('blocks', ['id' => $block->id]);
        expect($conversation->fresh()->archived_at)->toBeNull();
    }

    public function test_unblocking_keeps_the_conversation_archived_when_the_other_member_also_blocked(): void
    {
        $viewer = User::factory()->withProfile()->create();
        $member = User::factory()->withProfile()->create();
        Block::factory()->create([
            'blocker_user_id' => $viewer->id,
            'blocked_user_id' => $member->id,
        ]);
        Block::factory()->create([
            'blocker_user_id' => $member->id,
            'blocked_user_id' => $viewer->id,
        ]);
        $conversation = MemberMatch::factory()->create([
            'user_low_id' => min($viewer->id, $member->id),
            'user_high_id' => max($viewer->id, $member->id),
        ])->conversation()->create(['archived_at' => now()]);

        $this->actingAs($viewer)->delete(route('members.unblock', $member));

        $this->assertDatabaseMissing('blocks', [
            'blocker_user_id' => $viewer->id,
            'blocked_user_id' => $member->id,
        ]);
        $this->assertDatabaseHas('blocks', [
            'blocker_user_id' => $member->id,
            'blocked_user_id' => $viewer->id,
        ]);
        expect($conversation->fresh()->archived_at)->not->toBeNull();
    }

    public function test_unblocking_without_an_owned_block_keeps_an_archived_conversation_unchanged(): void
    {
        $viewer = User::factory()->withProfile()->create();
        $member = User::factory()->withProfile()->create();
        $conversation = MemberMatch::factory()->create([
            'user_low_id' => min($viewer->id, $member->id),
            'user_high_id' => max($viewer->id, $member->id),
        ])->conversation()->create(['archived_at' => now()]);

        $this->actingAs($viewer)->delete(route('members.unblock', $member));

        expect($conversation->fresh()->archived_at)->not->toBeNull();
    }
}
