<?php

namespace Tests\Feature;

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
}
