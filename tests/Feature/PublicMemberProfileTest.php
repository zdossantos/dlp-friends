<?php

namespace Tests\Feature;

use App\Models\Block;
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

    public function test_a_block_in_either_direction_makes_the_public_profile_unavailable(): void
    {
        $viewer = User::factory()->withProfile()->create();
        $member = User::factory()->withProfile()->create();

        foreach ([[$viewer, $member], [$member, $viewer]] as [$blocker, $blocked]) {
            Block::query()->delete();
            Block::factory()->create([
                'blocker_user_id' => $blocker->id,
                'blocked_user_id' => $blocked->id,
            ]);

            $this->actingAs($viewer)->get(route('members.show', $member))->assertNotFound();
        }
    }
}
