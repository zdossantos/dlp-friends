<?php

namespace Tests\Feature;

use App\Enums\ProfileVisibility;
use App\Enums\SwipeDecision;
use App\Enums\UserStatus;
use App\Models\Block;
use App\Models\MemberMatch;
use App\Models\Swipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LikeMemberControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_like_an_eligible_profile_once(): void
    {
        [$viewer, $member] = $this->members();

        $this->actingAs($viewer)
            ->post(route('members.like', $member))
            ->assertRedirect();

        expect(Swipe::query()
            ->whereBelongsTo($viewer, 'actor')
            ->whereBelongsTo($member, 'target')
            ->value('decision'))->toBe(SwipeDecision::Like);

        $this->actingAs($viewer)
            ->post(route('members.like', $member))
            ->assertSessionHasErrors('decision');
        $this->assertDatabaseCount('swipes', 1);
    }

    public function test_a_reciprocal_profile_like_creates_the_existing_match_flow_once(): void
    {
        Notification::fake();
        [$viewer, $member] = $this->members();
        Swipe::factory()->create([
            'actor_user_id' => $member->id,
            'target_user_id' => $viewer->id,
            'decision' => SwipeDecision::Like,
        ]);

        $this->actingAs($viewer)
            ->post(route('members.like', $member))
            ->assertRedirect(route('discovery.index'))
            ->assertSessionHas('discovery.match');

        $this->assertDatabaseCount('matches', 1);
        $this->assertDatabaseCount('conversations', 1);
        expect(MemberMatch::query()->first()?->conversation)->not->toBeNull();
    }

    public function test_a_reciprocal_like_can_return_to_a_safe_event_workspace_path(): void
    {
        [$viewer, $member] = $this->members();
        Swipe::factory()->create([
            'actor_user_id' => $member->id,
            'target_user_id' => $viewer->id,
            'decision' => SwipeDecision::Like,
        ]);

        $returnTo = '/events/12/participants/34?origin=mine';

        $this->actingAs($viewer)
            ->post(route('members.like', $member), ['return_to' => $returnTo])
            ->assertRedirect($returnTo)
            ->assertSessionHas('discovery.match');
    }

    public function test_a_profile_like_rejects_an_external_return_target(): void
    {
        [$viewer, $member] = $this->members();
        Swipe::factory()->create([
            'actor_user_id' => $member->id,
            'target_user_id' => $viewer->id,
            'decision' => SwipeDecision::Like,
        ]);

        $this->actingAs($viewer)
            ->post(route('members.like', $member), ['return_to' => '//example.com'])
            ->assertRedirect(route('discovery.index'));
    }

    public function test_a_blocked_pair_cannot_like_from_a_profile(): void
    {
        [$viewer, $member] = $this->members();
        Block::factory()->create([
            'blocker_user_id' => $member->id,
            'blocked_user_id' => $viewer->id,
        ]);

        $this->actingAs($viewer)
            ->post(route('members.like', $member))
            ->assertSessionHasErrors('target');
        $this->assertDatabaseCount('swipes', 0);
    }

    #[DataProvider('unavailableTargetProvider')]
    public function test_an_unavailable_profile_cannot_be_liked(string $state): void
    {
        $viewer = User::factory()->withProfile()->create();
        $member = User::factory()->withProfile()->create();

        match ($state) {
            'self' => $member = $viewer,
            'hidden' => $member->profile?->update(['visibility' => ProfileVisibility::Hidden]),
            'inactive' => $member->forceFill(['status' => UserStatus::PendingDeletion])->save(),
            'incomplete' => $member->profile?->update(['onboarding_completed_at' => null]),
        };

        $this->actingAs($viewer)
            ->post(route('members.like', $member))
            ->assertSessionHasErrors('target');
        $this->assertDatabaseCount('swipes', 0);
    }

    /** @return array<string, array{string}> */
    public static function unavailableTargetProvider(): array
    {
        return [
            'self' => ['self'],
            'hidden profile' => ['hidden'],
            'inactive account' => ['inactive'],
            'incomplete profile' => ['incomplete'],
        ];
    }

    /** @return array{User, User} */
    private function members(): array
    {
        return [
            User::factory()->withProfile()->create(),
            User::factory()->withProfile()->create(),
        ];
    }
}
