<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\MemberMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicMemberProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_complete_member_can_view_only_another_members_public_profile_data(): void
    {
        config()->set('inertia.testing.ensure_pages_exist', false);
        $viewer = User::factory()->withProfile()->create();
        $member = User::factory()->withProfile()->create();

        $this->actingAs($viewer)->get(route('members.show', $member))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Members/Show')
                ->where('member.id', $member->id)
                ->where('member.display_name', $member->profile->display_name)
                ->has('member.avatar')
                ->missing('member.email')
                ->missing('member.birth_date'));
    }

    public function test_a_blocked_pair_can_still_view_the_public_profile_without_revealing_an_incoming_block(): void
    {
        config()->set('inertia.testing.ensure_pages_exist', false);
        $viewer = User::factory()->withProfile()->create();
        $member = User::factory()->withProfile()->create();

        foreach ([[$viewer, $member, true], [$member, $viewer, false]] as [$blocker, $blocked, $canUnblock]) {
            Block::query()->delete();
            Block::factory()->create([
                'blocker_user_id' => $blocker->id,
                'blocked_user_id' => $blocked->id,
            ]);

            $this->actingAs($viewer)->get(route('members.show', $member))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('member.id', $member->id)
                    ->where('canUnblock', $canUnblock));
        }
    }

    public function test_a_profile_opened_from_a_shared_conversation_returns_to_that_conversation(): void
    {
        config()->set('inertia.testing.ensure_pages_exist', false);
        $viewer = User::factory()->withProfile()->create();
        $member = User::factory()->withProfile()->create();
        $conversation = MemberMatch::factory()->create([
            'user_low_id' => min($viewer->id, $member->id),
            'user_high_id' => max($viewer->id, $member->id),
        ])->conversation()->create();

        $this->actingAs($viewer)
            ->get(route('members.show', [
                'member' => $member,
                'conversation' => $conversation->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('backHref', route('conversations.show', $conversation, absolute: false)));
    }
}
