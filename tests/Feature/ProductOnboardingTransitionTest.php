<?php

namespace Tests\Feature;

use App\Actions\AdvanceProductOnboarding;
use App\Enums\ProductOnboardingStatus;
use App\Enums\ProductOnboardingStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductOnboardingTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_advances_only_through_the_required_order_without_social_writes(): void
    {
        $user = User::factory()->create();
        $action = app(AdvanceProductOnboarding::class);

        expect($action->start($user)->step)->toBe(ProductOnboardingStep::PassDemo)
            ->and($action->advance($user, ProductOnboardingStep::PassDemo)->step)->toBe(ProductOnboardingStep::LikeDemo)
            ->and($action->advance($user, ProductOnboardingStep::LikeDemo)->step)->toBe(ProductOnboardingStep::MatchDemo)
            ->and($action->advance($user, ProductOnboardingStep::MatchDemo)->step)->toBe(ProductOnboardingStep::ConversationDemo)
            ->and($action->complete($user)->status)->toBe(ProductOnboardingStatus::Completed);

        $this->assertDatabaseCount('swipes', 0);
        $this->assertDatabaseCount('matches', 0);
        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_it_rejects_an_out_of_order_action_without_changing_progress(): void
    {
        $user = User::factory()->create();
        $action = app(AdvanceProductOnboarding::class);
        $action->start($user);

        try {
            $action->advance($user, ProductOnboardingStep::LikeDemo);
            $this->fail('The out-of-order transition should have failed.');
        } catch (ValidationException) {
            expect($user->productOnboarding()->firstOrFail()->step)
                ->toBe(ProductOnboardingStep::PassDemo);
        }
    }

    public function test_start_resumes_existing_progress_without_rewinding_it(): void
    {
        $user = User::factory()->create();
        $action = app(AdvanceProductOnboarding::class);
        $action->start($user);
        $action->advance($user, ProductOnboardingStep::PassDemo);

        expect($action->start($user)->step)->toBe(ProductOnboardingStep::LikeDemo);
    }
}
