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
use Illuminate\Support\Facades\DB;
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
            'pass_display_name', 'pass_display_name_en', 'pass_bio', 'pass_bio_en',
            'like_display_name', 'like_display_name_en', 'like_bio', 'like_bio_en',
        ]))->toBeTrue();
    }

    public function test_existing_settings_receive_usable_localized_copy_defaults(): void
    {
        [$passAvatar, $likeAvatar] = Avatar::factory()->count(2)->create();
        $migration = require database_path(
            'migrations/2026_09_05_000000_add_localized_copy_to_product_onboarding_settings.php',
        );

        $migration->down();

        try {
            DB::table('product_onboarding_settings')->insert([
                'id' => ProductOnboardingSetting::SINGLETON_ID,
                'pass_avatar_id' => $passAvatar->id,
                'like_avatar_id' => $likeAvatar->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $migration->up();

            expect(DB::table('product_onboarding_settings')->first())
                ->pass_display_name->toBe('Camille')
                ->pass_bio->toBe('Aime découvrir les détails du parc et profiter des spectacles.')
                ->pass_display_name_en->toBe('Camille')
                ->pass_bio_en->toBe('Enjoys discovering park details and watching the shows.')
                ->like_display_name->toBe('Alex')
                ->like_bio->toBe('Toujours partant pour partager une journée conviviale entre fans.')
                ->like_display_name_en->toBe('Alex')
                ->like_bio_en->toBe('Always happy to share a park day with fellow fans.');
        } finally {
            if (! Schema::hasColumn('product_onboarding_settings', 'pass_display_name')) {
                $migration->up();
            }
        }
    }

    public function test_skipped_progress_is_normalized_to_the_mandatory_first_step(): void
    {
        $user = User::factory()->create();

        DB::table('product_onboardings')->insert([
            'user_id' => $user->id,
            'status' => 'skipped',
            'step' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path(
            'migrations/2026_08_28_110000_normalize_mandatory_product_onboarding_status.php',
        );
        $migration->up();

        $progress = DB::table('product_onboardings')
            ->where('user_id', $user->id)
            ->first();

        expect($progress)
            ->status->toBe(ProductOnboardingStatus::InProgress->value)
            ->step->toBe(ProductOnboardingStep::PassDemo->value);
    }
}
