<?php

namespace Tests\Feature;

use App\Enums\ProductOnboardingStatus;
use App\Enums\ProductOnboardingStep;
use App\Models\Avatar;
use App\Models\ProductOnboarding;
use App\Models\ProductOnboardingSetting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductOnboardingSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_is_unique_per_user_and_cast_to_enums(): void
    {
        $user = User::factory()->create();
        $progress = $user->productOnboarding()->create([
            'status' => ProductOnboardingStatus::InProgress,
            'step' => ProductOnboardingStep::PassDemo,
        ]);

        expect($progress->status)->toBe(ProductOnboardingStatus::InProgress)
            ->and($progress->step)->toBe(ProductOnboardingStep::PassDemo);

        $this->expectException(QueryException::class);
        ProductOnboarding::factory()->for($user)->create();
    }

    public function test_settings_require_two_distinct_avatars(): void
    {
        $avatar = Avatar::factory()->create();

        $this->expectException(QueryException::class);
        ProductOnboardingSetting::query()->create([
            'id' => ProductOnboardingSetting::SINGLETON_ID,
            'pass_avatar_id' => $avatar->id,
            'like_avatar_id' => $avatar->id,
        ]);
    }

    public function test_onboarding_tables_expose_the_expected_columns(): void
    {
        expect(Schema::hasColumns('product_onboardings', [
            'id', 'user_id', 'status', 'step', 'created_at', 'updated_at',
        ]))->toBeTrue()->and(Schema::hasColumns('product_onboarding_settings', [
            'id', 'pass_avatar_id', 'like_avatar_id', 'created_at', 'updated_at',
        ]))->toBeTrue();
    }
}
