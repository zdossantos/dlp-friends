<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_verified_adult_active_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->withProfile()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_incomplete_users_are_redirected_to_profile_onboarding(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('member-profile.create'));
    }

    public function test_unverified_users_are_redirected_to_the_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_inactive_users_cannot_visit_the_dashboard(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::PendingDeletion,
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    public function test_underage_users_cannot_visit_the_dashboard(): void
    {
        Carbon::setTestNow('2026-08-16');
        $user = User::factory()->create([
            'birth_date' => '2008-08-17',
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    public function test_users_without_a_birth_date_cannot_visit_the_dashboard(): void
    {
        $user = User::factory()->create([
            'birth_date' => null,
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }
}
