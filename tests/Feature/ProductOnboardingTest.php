<?php

namespace Tests\Feature;

use App\Enums\ProductOnboardingStatus;
use App\Enums\ProductOnboardingStep;
use App\Enums\ProfileVisibility;
use App\Enums\VisitFrequency;
use App\Models\Avatar;
use App\Models\ProductOnboarding;
use App\Models\ProductOnboardingSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_profile_completion_redirects_to_product_onboarding(): void
    {
        [$profileAvatar] = $this->configureDemoAvatars();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('member-profile.store'), $this->validProfilePayload($profileAvatar))
            ->assertRedirect(route('onboarding.show'));
    }

    public function test_product_onboarding_exposes_only_tutorial_presentation_data(): void
    {
        config()->set('inertia.testing.ensure_pages_exist', false);
        [$passAvatar, $likeAvatar] = $this->configureDemoAvatars();
        $user = User::factory()->withProfile()->create();

        $this->actingAs($user)
            ->get(route('onboarding.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Onboarding/Show')
                ->where('status', ProductOnboardingStatus::InProgress->value)
                ->where('step', ProductOnboardingStep::PassDemo->value)
                ->has('demoProfiles', 2)
                ->where('demoProfiles.0.avatar', [
                    'name' => $passAvatar->name,
                    'imageUrl' => route('avatars.image', $passAvatar),
                    'primaryColor' => $passAvatar->primary_color,
                    'secondaryColor' => $passAvatar->secondary_color,
                ])
                ->where('demoProfiles.1.avatar', [
                    'name' => $likeAvatar->name,
                    'imageUrl' => route('avatars.image', $likeAvatar),
                    'primaryColor' => $likeAvatar->primary_color,
                    'secondaryColor' => $likeAvatar->secondary_color,
                ])
                ->missing('userId')
                ->missing('matchId')
                ->missing('conversationId'));
    }

    public function test_completed_tutorial_does_not_auto_launch(): void
    {
        $user = User::factory()->withProfile()->create();
        ProductOnboarding::factory()->for($user)->completed()->create();

        $this->actingAs($user)->get(route('app'))
            ->assertRedirect(route('discovery.index'));
    }

    public function test_not_started_and_in_progress_tutorials_auto_launch(): void
    {
        $notStarted = User::factory()->withProfile()->create();
        $inProgress = User::factory()->withProfile()->create();
        ProductOnboarding::factory()->for($inProgress)->create([
            'status' => ProductOnboardingStatus::InProgress,
            'step' => ProductOnboardingStep::LikeDemo,
        ]);

        $this->actingAs($notStarted)->get(route('app'))
            ->assertRedirect(route('onboarding.show'));
        $this->actingAs($inProgress)->get(route('app'))
            ->assertRedirect(route('onboarding.show'));
    }

    public function test_onboarding_is_unavailable_without_two_distinct_active_demo_avatars(): void
    {
        $user = User::factory()->withProfile()->create();

        $this->actingAs($user)->get(route('onboarding.show'))
            ->assertStatus(503);
    }

    public function test_incomplete_onboarding_blocks_member_pages_but_not_its_own_routes(): void
    {
        $this->configureDemoAvatars();
        $user = User::factory()->withProfile()->create();

        $this->actingAs($user)->get(route('discovery.index'))
            ->assertRedirect(route('onboarding.show'));
        $this->actingAs($user)->get(route('account.edit'))
            ->assertRedirect(route('onboarding.show'));
        $this->actingAs($user)->get(route('onboarding.show'))
            ->assertOk();
    }

    public function test_incomplete_profile_is_redirected_to_profile_creation_before_onboarding(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('discovery.index'))
            ->assertRedirect(route('member-profile.create'));
    }

    public function test_authenticated_member_can_advance_and_complete_without_social_identifiers(): void
    {
        $this->configureDemoAvatars();
        $user = User::factory()->withProfile()->create();
        $this->actingAs($user)->get(route('onboarding.show'))->assertOk();

        foreach ([
            ProductOnboardingStep::PassDemo,
            ProductOnboardingStep::LikeDemo,
            ProductOnboardingStep::MatchDemo,
        ] as $step) {
            $this->actingAs($user)->patch(route('onboarding.advance'), ['step' => $step->value])
                ->assertRedirect(route('onboarding.show'));
        }

        $this->actingAs($user)->post(route('onboarding.complete'))
            ->assertRedirect(route('discovery.index'));

        $this->assertDatabaseCount('swipes', 0);
        $this->assertDatabaseCount('matches', 0);
        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_tutorial_escape_routes_do_not_exist(): void
    {
        $user = User::factory()->withProfile()->create();

        $this->actingAs($user)->post('/onboarding/skip')->assertNotFound();
        $this->actingAs($user)->post('/onboarding/restart')->assertNotFound();
        $this->actingAs($user)->get('/settings/onboarding')->assertNotFound();
        $this->actingAs($user)->post('/settings/onboarding/restart')->assertNotFound();
    }

    /** @return array{Avatar, Avatar} */
    private function configureDemoAvatars(): array
    {
        [$passAvatar, $likeAvatar] = Avatar::factory()->count(2)->create();
        ProductOnboardingSetting::query()->create([
            'id' => ProductOnboardingSetting::SINGLETON_ID,
            'pass_avatar_id' => $passAvatar->id,
            'like_avatar_id' => $likeAvatar->id,
        ]);

        return [$passAvatar, $likeAvatar];
    }

    /** @return array<string, mixed> */
    private function validProfilePayload(Avatar $avatar): array
    {
        return [
            'avatar_id' => $avatar->id,
            'display_name' => 'Magic Friend',
            'bio' => null,
            'visit_frequency' => VisitFrequency::Often->value,
            'visibility' => ProfileVisibility::Visible->value,
        ];
    }
}
