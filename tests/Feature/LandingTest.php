<?php

namespace Tests\Feature;

use App\Enums\ProductOnboardingStatus;
use App\Enums\UserStatus;
use App\Models\ProductOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/app')->assertRedirect(route('login'));
    }

    public function test_unverified_member_is_redirected_to_verification(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/app')->assertRedirect(route('verification.notice'));
    }

    public function test_ineligible_member_cannot_use_the_landing_route(): void
    {
        $user = User::factory()->create(['status' => UserStatus::PendingDeletion]);

        $this->actingAs($user)->get('/app')->assertForbidden();
    }

    public function test_member_without_a_complete_profile_lands_on_onboarding(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app')
            ->assertRedirect(route('member-profile.create'));
    }

    public function test_complete_member_lands_on_discovery(): void
    {
        $user = User::factory()->withProfile()->create();
        ProductOnboarding::factory()->for($user)->create([
            'status' => ProductOnboardingStatus::Completed,
            'step' => null,
        ]);

        $this->actingAs($user)
            ->get('/app')
            ->assertRedirect(route('discovery.index'));
    }

    public function test_complete_admin_lands_on_the_dashboard(): void
    {
        $admin = User::factory()->withProfile()->admin()->create();
        ProductOnboarding::factory()->for($admin)->create([
            'status' => ProductOnboardingStatus::Skipped,
            'step' => null,
        ]);

        $this->actingAs($admin)
            ->get('/app')
            ->assertRedirect(route('dashboard'));
    }
}
