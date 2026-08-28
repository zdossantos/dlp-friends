<?php

namespace Tests\Feature;

use App\Enums\ProductOnboardingStatus;
use App\Enums\ProductOnboardingStep;
use App\Models\ProductOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductOnboardingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_view_their_tutorial_status(): void
    {
        config()->set('inertia.testing.ensure_pages_exist', false);
        $user = User::factory()->withProfile()->create();
        $progress = ProductOnboarding::factory()->for($user)->create([
            'status' => ProductOnboardingStatus::Completed,
            'step' => null,
        ]);

        $this->actingAs($user)->get(route('onboarding-settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Onboarding')
                ->where('onboarding.status', ProductOnboardingStatus::Completed->value)
                ->where('onboarding.step', null)
                ->where('onboarding.updatedAt', $progress->updated_at?->toIso8601String()));
    }

    public function test_member_can_relaunch_the_tutorial_from_settings(): void
    {
        $user = User::factory()->withProfile()->create();
        ProductOnboarding::factory()->for($user)->create([
            'status' => ProductOnboardingStatus::Skipped,
            'step' => null,
        ]);

        $this->actingAs($user)->post(route('onboarding-settings.restart'))
            ->assertRedirect(route('onboarding.show'));

        $this->assertDatabaseHas('product_onboardings', [
            'user_id' => $user->id,
            'status' => ProductOnboardingStatus::InProgress->value,
            'step' => ProductOnboardingStep::PassDemo->value,
        ]);
    }
}
